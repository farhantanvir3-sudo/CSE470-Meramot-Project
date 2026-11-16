<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FinancialLedger;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ShopService;
use App\Models\VendorProfile;
use App\Services\MultiChannelNotificationService;
use App\Services\PdfInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * FEATURE R17: Online Payment Gateway (SSLCommerz / MFS Escrow) & PDF Invoice
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Sprint: Sprint 2
 * Architectural Layer: Controller (PaymentGatewayController)
 * Models Touched: PaymentTransaction, Invoice, InvoiceLineItem, EscrowLedger,
 *                 FinancialLedger, AuditLog
 * Views Rendered: checkout/payment.blade.php, checkout/sandbox-gateway.blade.php,
 *                 invoices/show.blade.php
 * ============================================================================
 */
class PaymentGatewayController extends Controller
{
    /**
     * Compute deterministic checkout subtotal, delivery fee, discount, and line items.
     */
    public static function computeCheckoutBreakdown(RepairRequest $repairRequest): array
    {
        $isDirectService = ($repairRequest->source ?? '') === 'DIRECT_SERVICE';
        $discountAmount = round(max(0.0, (float) ($repairRequest->discount_amount ?? 0)), 2);

        $shopId = $repairRequest->repairJob?->shop_id
            ?? $repairRequest->requested_shop_id
            ?? $repairRequest->bids?->firstWhere('id', $repairRequest->selected_bid_id)?->shop_id;
        $shop = $shopId ? RepairShop::with('vendorProfile')->find($shopId) : null;
        $commissionRatePct = ServicePackageController::resolveCommissionRatePct($shop);

        if ($isDirectService) {
            $quoteItems = is_array($repairRequest->repairJob?->final_quote_items)
                ? $repairRequest->repairJob->final_quote_items
                : [];
            $explicitDeliveryFee = isset($quoteItems[0]['delivery_fee']) && is_numeric($quoteItems[0]['delivery_fee'])
                ? (float) $quoteItems[0]['delivery_fee']
                : null;

            $deliveryFee = !$repairRequest->requiresCourierLogistics()
                ? 0.0
                : ($explicitDeliveryFee !== null
                    ? round($explicitDeliveryFee, 2)
                    : (($repairRequest->delivery_type === 'EXPRESS') ? 150.0 : 80.0));
            $storedTotal = round((float) ($repairRequest->quoted_final_amount ?? 0), 2);
            $storedCommission = round((float) ($repairRequest->commission_amount ?? $repairRequest->checkup_fee ?? 0), 2);

            $servicesSubtotal = !empty($quoteItems)
                ? round((float) collect($quoteItems)->sum(
                    fn ($it) => ((float) ($it['price'] ?? 0)) * max(1, (int) ($it['quantity'] ?? 1))
                ), 2)
                : round(max(0.0, $storedTotal + $discountAmount - $storedCommission - $deliveryFee), 2);

            $escrowFee = $storedCommission > 0
                ? $storedCommission
                : round($servicesSubtotal * ($commissionRatePct / 100.0), 2);
            $effectiveRatePct = ($servicesSubtotal > 0 && $escrowFee > 0)
                ? round(($escrowFee / $servicesSubtotal) * 100, 2)
                : $commissionRatePct;

            $computedTotal = max(0.0, round($servicesSubtotal + $escrowFee + $deliveryFee - $discountAmount, 2));
            $totalPayable = $storedTotal > 0 ? $storedTotal : $computedTotal;

            // Keep servicesSubtotal + escrowFee + delivery - discount == totalPayable invariant exact
            if (abs(($servicesSubtotal + $escrowFee + $deliveryFee - $discountAmount) - $totalPayable) > 0.02) {
                $discountAmount = max(0.0, round(($servicesSubtotal + $escrowFee + $deliveryFee) - $totalPayable, 2));
            }

            $lineItems = [];
            foreach ($quoteItems as $it) {
                $qty = max(1, (int) ($it['quantity'] ?? 1));
                $unit = round((float) ($it['price'] ?? 0), 2);
                $lineItems[] = [
                    'shop_service_id' => isset($it['service_id']) ? (int) $it['service_id'] : null,
                    'title' => (string) ($it['title'] ?? 'Booked Workshop Service'),
                    'description' => 'Verified workshop service & hardware listing',
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'total_price' => round($unit * $qty, 2),
                ];
            }
            if (empty($lineItems) && $servicesSubtotal > 0) {
                $lineItems[] = [
                    'shop_service_id' => $repairRequest->requested_service_id,
                    'title' => $repairRequest->title ?: 'Direct-Order Workshop Service',
                    'description' => 'Verified workshop service & hardware listing',
                    'quantity' => 1,
                    'unit_price' => $servicesSubtotal,
                    'total_price' => $servicesSubtotal,
                ];
            }

            return [
                'is_direct_service' => true,
                'services_subtotal' => $servicesSubtotal,
                'vendor_earning' => $servicesSubtotal,
                'escrow_fee' => $escrowFee,
                'platform_fee' => $escrowFee,
                'commission_rate_pct' => $effectiveRatePct,
                'invoice_subtotal' => $servicesSubtotal,
                'delivery_fee' => round($deliveryFee, 2),
                'discount_amount' => $discountAmount,
                'total_payable' => round($totalPayable, 2),
                'quote_items' => $quoteItems,
                'line_items' => $lineItems,
            ];
        }

        $acceptedBid = $repairRequest->bids?->firstWhere('id', $repairRequest->selected_bid_id)
            ?? $repairRequest->repairJob?->acceptedBid
            ?? $repairRequest->bids?->where('status', 'ACCEPTED')->first()
            ?? $repairRequest->bids?->sortBy('proposed_cost')->first();

        $acceptedBidCost = round((float) (
            $repairRequest->repairJob?->final_quoted_amount
            ?? $repairRequest->quoted_final_amount
            ?? $acceptedBid?->proposed_cost
            ?? 3500
        ), 2);

        $escrowFee = round($acceptedBidCost * ($commissionRatePct / 100.0), 2);
        $deliveryFee = $repairRequest->requiresCourierLogistics()
            ? (($repairRequest->delivery_type === 'REGULAR') ? 80.0 : 150.0)
            : 0.0;
        $totalPayable = max(0.0, round($acceptedBidCost + $escrowFee + $deliveryFee - $discountAmount, 2));

        $lineItems = [];
        $job = $repairRequest->repairJob;
        $partsItems = $job ? $job->resolvedPartsItems() : ($acceptedBid ? $acceptedBid->resolvedPartsItems() : []);
        $partsSum = round((float) collect($partsItems)->sum('cost'), 2);
        $laborCost = $job ? $job->resolvedLaborCost() : round((float) ($acceptedBid->labor_cost ?? 0), 2);

        if (!empty($partsItems) && abs(($partsSum + $laborCost) - $acceptedBidCost) <= 0.02) {
            foreach ($partsItems as $part) {
                $cost = round((float) ($part['cost'] ?? 0), 2);
                if ($cost <= 0) {
                    continue;
                }
                $partDays = (int) ($part['warranty_days'] ?? 0);
                $lineItems[] = [
                    'shop_service_id' => null,
                    'title' => (string) ($part['name'] ?? 'Replacement Component'),
                    'description' => (!empty($part['has_warranty']) && $partDays > 0)
                        ? "Replacement hardware • {$partDays}-day warranty"
                        : 'Replacement hardware component',
                    'quantity' => 1,
                    'unit_price' => $cost,
                    'total_price' => $cost,
                ];
            }
            $remainingLabor = round($acceptedBidCost - $partsSum, 2);
            if ($remainingLabor > 0) {
                $lineItems[] = [
                    'shop_service_id' => null,
                    'title' => 'Certified Technician Labor & Calibration',
                    'description' => $job?->diagnosis_notes ?: 'Workshop bench diagnostics & assembly',
                    'quantity' => 1,
                    'unit_price' => $remainingLabor,
                    'total_price' => $remainingLabor,
                ];
            }
        }

        if (empty($lineItems)) {
            $lineItems[] = [
                'shop_service_id' => null,
                'title' => $repairRequest->title ?: 'Verified Electronics Repair',
                'description' => $job?->diagnosis_notes ?: 'Approved technician repair quote',
                'quantity' => 1,
                'unit_price' => $acceptedBidCost,
                'total_price' => $acceptedBidCost,
            ];
        }

        return [
            'is_direct_service' => false,
            'services_subtotal' => $acceptedBidCost,
            'vendor_earning' => $acceptedBidCost,
            'escrow_fee' => $escrowFee,
            'platform_fee' => $escrowFee,
            'commission_rate_pct' => $commissionRatePct,
            'invoice_subtotal' => $acceptedBidCost,
            'delivery_fee' => round($deliveryFee, 2),
            'discount_amount' => $discountAmount,
            'total_payable' => $totalPayable,
            'quote_items' => [],
            'line_items' => $lineItems,
        ];
    }

    /**
     * Show payment checkout screen.
     * Route: GET /checkout/payment/{requestId}
     */
    public function showCheckout(Request $request, $requestId)
    {
        $repairRequest = RepairRequest::with([
            'bids.shop',
            'requestedShop',
            'repairJob.acceptedBid',
            'repairJob.shop',
            'invoices.lineItems',
            'payments',
        ])->find($requestId);

        if (!$repairRequest) {
            return app(ComingSoonController::class)->show($request, 'sslcommerz-payment');
        }

        $breakdown = self::computeCheckoutBreakdown($repairRequest);
        $paidTransaction = $repairRequest->payments
            ->where('status', 'PAID')
            ->sortByDesc('id')
            ->first();
        $latestInvoice = $repairRequest->invoices->sortByDesc('id')->first();
        $preferredChannel = strtoupper((string) $request->query('method', 'BKASH'));
        if (!in_array($preferredChannel, ['BKASH', 'NAGAD', 'ROCKET', 'CARD', 'SSLCOMMERZ'], true)) {
            $preferredChannel = 'BKASH';
        }

        return view('checkout.payment', compact(
            'repairRequest',
            'breakdown',
            'paidTransaction',
            'latestInvoice',
            'preferredChannel'
        ));
    }

    /**
     * Initiate payment transaction via SSLCommerz / MFS Escrow Gateway.
     * Route: POST /payment/initiate
     */
    public function initiatePayment(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect('/login')->with('warning', 'Please sign in to complete your payment.');
        }

        $validated = $request->validate([
            'repair_request_id' => 'required|integer|exists:repair_requests,id',
            'payment_method' => 'nullable|string|in:SSLCOMMERZ,BKASH,NAGAD,CARD,ROCKET',
        ]);

        $repairRequest = RepairRequest::with([
            'customer',
            'bids.shop',
            'requestedShop',
            'repairJob.acceptedBid',
            'repairJob.shop',
            'invoices',
            'payments',
        ])->findOrFail($validated['repair_request_id']);

        if ((int) $repairRequest->customer_id !== (int) $user->id && $user->role !== 'admin') {
            abort(403, 'Only the order owner or an administrator can initiate payment for this repair request.');
        }

        $alreadyPaid = $repairRequest->payments->where('status', 'PAID')->sortByDesc('id')->first();
        if ($alreadyPaid && $alreadyPaid->invoice_id) {
            return redirect()
                ->route('invoices.show', $alreadyPaid->invoice_id)
                ->with('success', 'This repair order has already been paid and secured with Meramot Buyer Protection.');
        }

        $rawChannel = strtoupper((string) ($validated['payment_method'] ?? 'SSLCOMMERZ'));
        $dbPaymentMethod = $rawChannel === 'ROCKET' ? 'SSLCOMMERZ' : $rawChannel;

        $breakdown = self::computeCheckoutBreakdown($repairRequest);
        $shopId = $repairRequest->repairJob?->shop_id
            ?? $repairRequest->requested_shop_id
            ?? $repairRequest->bids?->firstWhere('id', $repairRequest->selected_bid_id)?->shop_id;

        $primarySvcId = $shopId ? ShopService::where('shop_id', $shopId)->value('id') : null;

        [$invoice, $payment] = DB::transaction(function () use ($repairRequest, $user, $breakdown, $shopId, $primarySvcId, $dbPaymentMethod, $rawChannel) {
            $now = now();
            $invoiceSubtotal = $breakdown['invoice_subtotal'];
            $commissionAmount = round((float) ($breakdown['escrow_fee'] ?? 0), 2);

            $invoice = Invoice::where('repair_request_id', $repairRequest->id)
                ->orderByDesc('id')
                ->first();

            if ($invoice) {
                $invoice->update([
                    'repair_job_id' => $repairRequest->repairJob?->id ?? $invoice->repair_job_id,
                    'shop_id' => $shopId ?? $invoice->shop_id,
                    'type' => 'FINAL',
                    'status' => $invoice->status === 'PAID' ? 'PAID' : 'ISSUED',
                    'subtotal_amount' => $invoiceSubtotal,
                    'delivery_amount' => $breakdown['delivery_fee'],
                    'discount_amount' => $breakdown['discount_amount'],
                    'commission_amount' => $commissionAmount,
                    'total_amount' => $breakdown['total_payable'],
                    'issued_at' => $invoice->issued_at ?? $now,
                    'due_at' => $invoice->due_at ?? $now->copy()->addDays(2),
                ]);
                $invoice->lineItems()->delete();
            } else {
                $invoice = Invoice::create([
                    'repair_request_id' => $repairRequest->id,
                    'repair_job_id' => $repairRequest->repairJob?->id,
                    'shop_id' => $shopId,
                    'type' => 'FINAL',
                    'status' => 'ISSUED',
                    'invoice_number' => 'INV-' . $now->format('Y') . '-' . str_pad((string) $repairRequest->id, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(Str::random(3)),
                    'subtotal_amount' => $invoiceSubtotal,
                    'delivery_amount' => $breakdown['delivery_fee'],
                    'discount_amount' => $breakdown['discount_amount'],
                    'commission_amount' => $commissionAmount,
                    'total_amount' => $breakdown['total_payable'],
                    'issued_at' => $now,
                    'due_at' => $now->copy()->addDays(2),
                ]);
            }

            foreach ($breakdown['line_items'] as $item) {
                $invoice->lineItems()->create([
                    'shop_service_id' => $item['shop_service_id'] ?? $primarySvcId,
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                ]);
            }

            $payment = PaymentTransaction::create([
                'user_id' => $repairRequest->customer_id,
                'repair_request_id' => $repairRequest->id,
                'invoice_id' => $invoice->id,
                'amount' => $breakdown['total_payable'],
                'currency' => 'BDT',
                'payment_method' => $dbPaymentMethod,
                'status' => 'PENDING',
                'transaction_reference' => 'MRM-TXN-' . strtoupper(Str::random(10)),
                'gateway_response' => json_encode([
                    'mode' => 'escrow_gateway_init',
                    'requested_channel' => $rawChannel,
                    'initiated_by_user_id' => $user->id,
                    'initiated_at' => $now->toIso8601String(),
                ]),
            ]);

            return [$invoice, $payment];
        });

        // Optional live SSLCommerz Sandbox/Production redirect when merchant keys are explicitly enabled
        $sslConfig = config('services.sslcommerz', []);
        $liveRedirect = filter_var($sslConfig['live_redirect'] ?? env('SSLCOMMERZ_LIVE_REDIRECT', false), FILTER_VALIDATE_BOOLEAN);
        $storeId = (string) ($sslConfig['store_id'] ?? '');
        $storePassword = (string) ($sslConfig['store_password'] ?? '');

        if ($liveRedirect && $storeId !== '' && $storePassword !== '' && !in_array($storeId, ['meramot_sandbox', 'testbox'], true)) {
            try {
                $apiDomain = rtrim((string) ($sslConfig['api_domain'] ?? 'https://sandbox.sslcommerz.com'), '/');
                $response = Http::asForm()->timeout(8)->post("{$apiDomain}/gwprocess/v4/api.php", [
                    'store_id' => $storeId,
                    'store_passwd' => $storePassword,
                    'total_amount' => number_format((float) $payment->amount, 2, '.', ''),
                    'currency' => 'BDT',
                    'tran_id' => $payment->transaction_reference,
                    'success_url' => url('/payment/success'),
                    'fail_url' => url('/payment/fail'),
                    'cancel_url' => url('/payment/cancel'),
                    'ipn_url' => url('/payment/ipn'),
                    'cus_name' => $repairRequest->customer?->name ?? 'Meramot Customer',
                    'cus_email' => $repairRequest->customer?->email ?? 'customer@meramot.bd',
                    'cus_add1' => $repairRequest->pickup_address ?: 'Dhaka, Bangladesh',
                    'cus_city' => 'Dhaka',
                    'cus_country' => 'Bangladesh',
                    'cus_phone' => $repairRequest->contact_phone ?: ($repairRequest->customer?->phone ?? '01700000000'),
                    'shipping_method' => 'NO',
                    'product_name' => $repairRequest->title ?: 'Electronics Repair Payment',
                    'product_category' => 'Electronics Repair',
                    'product_profile' => 'general',
                ]);

                if ($response->successful() && !empty($response->json('GatewayPageURL'))) {
                    return redirect()->away($response->json('GatewayPageURL'));
                }
            } catch (\Throwable $e) {
                // Fall back cleanly to Meramot Hosted Sandbox Gateway Simulator
            }
        }

        return redirect()->route('payment.sandbox', [
            'transactionRef' => $payment->transaction_reference,
            'channel' => strtolower($rawChannel),
        ]);
    }

    /**
     * Interactive Bangladeshi SSLCommerz / MFS Escrow Sandbox Simulator.
     * Route: GET /payment/sandbox/{transactionRef}
     */
    public function showSandbox(Request $request, string $transactionRef)
    {
        $payment = PaymentTransaction::with([
            'repairRequest.customer',
            'invoice.lineItems',
            'invoice.shop',
        ])->where('transaction_reference', $transactionRef)->firstOrFail();

        if ($request->user() && (int) $payment->user_id !== (int) $request->user()->id && $request->user()->role !== 'admin') {
            abort(403, 'Unauthorized payment session.');
        }

        if ($payment->status === 'PAID' && $payment->invoice_id) {
            return redirect()->route('invoices.show', ['invoice' => $payment->invoice_id, 'paid' => 1]);
        }

        $gatewayMeta = json_decode((string) $payment->gateway_response, true) ?: [];
        $activeChannel = strtoupper((string) ($request->query('channel') ?: ($gatewayMeta['requested_channel'] ?? $payment->payment_method ?: 'BKASH')));
        if (!in_array($activeChannel, ['BKASH', 'NAGAD', 'ROCKET', 'CARD', 'SSLCOMMERZ'], true)) {
            $activeChannel = 'BKASH';
        }

        return view('checkout.sandbox-gateway', compact('payment', 'activeChannel'));
    }

    /**
     * SSLCommerz / Sandbox success callback handler.
     * Route: POST /payment/success
     */
    public function handleSuccess(Request $request, MultiChannelNotificationService $notifier)
    {
        $tranRef = (string) ($request->input('tran_id') ?: $request->input('transaction_reference'));
        if ($tranRef === '') {
            return redirect('/')->with('error', 'Missing payment transaction reference.');
        }

        $payment = PaymentTransaction::with([
            'repairRequest.customer',
            'repairRequest.repairJob.shop.vendorProfile',
            'invoice',
        ])->where('transaction_reference', $tranRef)->firstOrFail();

        $invoice = $this->finalizeSuccessfulPayment($payment, $request->all(), $notifier);

        return redirect()
            ->route('invoices.show', ['invoice' => $invoice->id, 'paid' => 1])
            ->with('success', "Payment of ৳" . number_format((float) $payment->amount, 2) . " authorized! Official receipt {$invoice->invoice_number} is ready.");
    }

    /**
     * SSLCommerz / Sandbox failed payment callback handler.
     * Route: POST /payment/fail
     */
    public function handleFail(Request $request)
    {
        $tranRef = (string) ($request->input('tran_id') ?: $request->input('transaction_reference'));
        $payment = PaymentTransaction::where('transaction_reference', $tranRef)->firstOrFail();

        if ($payment->status !== 'PAID') {
            $payment->update([
                'status' => 'FAILED',
                'gateway_response' => json_encode([
                    'mode' => 'sandbox_simulator',
                    'outcome' => 'FAILED',
                    'reason' => $request->input('error_reason', 'Authorization declined by issuer / sandbox test failure.'),
                    'failed_at' => now()->toIso8601String(),
                ]),
            ]);
        }

        return redirect()
            ->route('payment.checkout', $payment->repair_request_id)
            ->with('error', 'Payment authorization failed or was declined. No funds were deducted — please retry with another MFS wallet or card.');
    }

    /**
     * SSLCommerz / Sandbox customer cancellation handler.
     * Route: POST /payment/cancel
     */
    public function handleCancel(Request $request)
    {
        $tranRef = (string) ($request->input('tran_id') ?: $request->input('transaction_reference'));
        $payment = PaymentTransaction::where('transaction_reference', $tranRef)->firstOrFail();

        if ($payment->status === 'PENDING') {
            $payment->update([
                'status' => 'FAILED',
                'gateway_response' => json_encode([
                    'mode' => 'sandbox_simulator',
                    'outcome' => 'CANCELLED',
                    'cancelled_at' => now()->toIso8601String(),
                ]),
            ]);
        }

        return redirect()
            ->route('payment.checkout', $payment->repair_request_id)
            ->with('warning', 'Payment session cancelled. Your repair order remains active and can be paid online or upon return.');
    }

    /**
     * Asynchronous SSLCommerz Instant Payment Notification (IPN) webhook.
     * Route: POST /payment/ipn (CSRF-exempt)
     */
    public function handleIpn(Request $request, MultiChannelNotificationService $notifier)
    {
        $tranRef = (string) ($request->input('tran_id') ?: $request->input('transaction_reference'));
        if ($tranRef === '') {
            return response()->json(['ok' => false, 'message' => 'Missing tran_id'], 422);
        }

        $payment = PaymentTransaction::with([
            'repairRequest.customer',
            'repairRequest.repairJob.shop.vendorProfile',
            'invoice',
        ])->where('transaction_reference', $tranRef)->first();

        if (!$payment) {
            return response()->json(['ok' => false, 'message' => 'Transaction not found'], 404);
        }

        $gatewayStatus = strtoupper((string) $request->input('status', 'VALID'));
        if (in_array($gatewayStatus, ['VALID', 'VALIDATED', 'PAID', 'SUCCESS'], true)) {
            $invoice = $this->finalizeSuccessfulPayment($payment, $request->all(), $notifier);
            return response()->json([
                'ok' => true,
                'status' => 'PAID',
                'transaction_reference' => $payment->transaction_reference,
                'invoice_number' => $invoice->invoice_number,
            ]);
        }

        if ($payment->status !== 'PAID') {
            $payment->update([
                'status' => 'FAILED',
                'gateway_response' => json_encode([
                    'mode' => 'ipn_webhook',
                    'gateway_status' => $gatewayStatus,
                    'payload' => $request->except(['store_passwd']),
                ]),
            ]);
        }

        return response()->json([
            'ok' => true,
            'status' => $payment->status,
            'transaction_reference' => $payment->transaction_reference,
        ]);
    }

    /**
     * View official HTML Invoice & Escrow Receipt.
     * Route: GET /invoices/{invoice}
     */
    public function showInvoice(Request $request, Invoice $invoice)
    {
        $invoice->loadMissing([
            'lineItems',
            'shop.vendorProfile',
            'repairRequest.customer',
            'repairRequest.requestedShop.vendorProfile',
            'repairJob.shop.vendorProfile',
            'repairJob.warranty',
            'paymentTransactions',
        ]);

        $this->authorizeInvoiceViewer($request, $invoice);

        $paidTransaction = $invoice->paymentTransactions
            ->where('status', 'PAID')
            ->sortByDesc('id')
            ->first() ?? $invoice->paymentTransactions->sortByDesc('id')->first();

        return view('invoices.show', compact('invoice', 'paidTransaction'));
    }

    /**
     * Convenience redirect from RepairRequest ID to its latest official Invoice.
     * Route: GET /requests/{requestId}/invoice
     */
    public function showRequestInvoice(Request $request, $requestId)
    {
        $invoice = Invoice::where('repair_request_id', $requestId)
            ->orderByRaw("CASE WHEN status = 'PAID' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->firstOrFail();

        return redirect()->route('invoices.show', $invoice->id);
    }

    /**
     * Download Official Pure-PHP PDF 1.4 Invoice.
     * Route: GET /invoices/{invoice}/pdf
     */
    public function downloadPdf(Request $request, Invoice $invoice, PdfInvoiceService $pdfService)
    {
        $invoice->loadMissing([
            'lineItems',
            'shop.vendorProfile',
            'repairRequest.customer',
            'repairRequest.requestedShop.vendorProfile',
            'repairJob.shop.vendorProfile',
            'repairJob.warranty',
            'paymentTransactions',
        ]);

        $this->authorizeInvoiceViewer($request, $invoice);

        $pdfBytes = $pdfService->generate($invoice);
        $safeFilename = preg_replace('/[^A-Za-z0-9\-_]/', '_', $invoice->invoice_number ?: ('INV-' . $invoice->id)) . '.pdf';

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $safeFilename . '"',
            'Content-Length' => (string) strlen($pdfBytes),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * Atomically settle a successful online payment into PaymentTransaction, Invoice, EscrowLedger & FinancialLedger.
     */
    protected function finalizeSuccessfulPayment(
        PaymentTransaction $payment,
        array $payload,
        MultiChannelNotificationService $notifier
    ): Invoice {
        $wasAlreadyPaid = $payment->status === 'PAID';

        $invoice = DB::transaction(function () use ($payment, $payload) {
            $now = now();
            $req = $payment->repairRequest;
            $job = $req?->repairJob;
            $selectedChannel = strtoupper((string) ($payload['channel'] ?? $payload['card_type'] ?? $payment->payment_method ?? 'SSLCOMMERZ'));
            $dbMethod = in_array($selectedChannel, ['BKASH', 'NAGAD', 'CARD', 'CASH', 'SSLCOMMERZ'], true)
                ? $selectedChannel
                : 'SSLCOMMERZ';

            $shopId = $payment->invoice?->shop_id
                ?? $job?->shop_id
                ?? $req?->requested_shop_id
                ?? $req?->bids?->firstWhere('id', $req->selected_bid_id)?->shop_id;

            $invoice = $payment->invoice;
            if (!$invoice) {
                $breakdown = $req ? self::computeCheckoutBreakdown($req) : [
                    'invoice_subtotal' => (float) $payment->amount,
                    'escrow_fee' => 0.0,
                    'delivery_fee' => 0.0,
                    'discount_amount' => 0.0,
                    'total_payable' => (float) $payment->amount,
                    'line_items' => [[
                        'shop_service_id' => null,
                        'title' => $req?->title ?: 'Repair Payment',
                        'description' => 'Verified workshop service & hardware listing',
                        'quantity' => 1,
                        'unit_price' => (float) $payment->amount,
                        'total_price' => (float) $payment->amount,
                    ]],
                ];

                $invoice = Invoice::create([
                    'repair_request_id' => $req?->id,
                    'repair_job_id' => $job?->id,
                    'shop_id' => $shopId,
                    'type' => 'FINAL',
                    'status' => 'PAID',
                    'invoice_number' => 'INV-' . $now->format('Y') . '-' . str_pad((string) ($req?->id ?? $payment->id), 4, '0', STR_PAD_LEFT) . '-' . strtoupper(Str::random(3)),
                    'subtotal_amount' => $breakdown['invoice_subtotal'],
                    'delivery_amount' => $breakdown['delivery_fee'],
                    'discount_amount' => $breakdown['discount_amount'],
                    'commission_amount' => round((float) ($breakdown['escrow_fee'] ?? 0), 2),
                    'total_amount' => $breakdown['total_payable'],
                    'issued_at' => $now,
                    'due_at' => $now,
                    'paid_at' => $now,
                ]);

                foreach ($breakdown['line_items'] as $li) {
                    $invoice->lineItems()->create($li);
                }
            } else {
                $invoice->update([
                    'status' => 'PAID',
                    'shop_id' => $invoice->shop_id ?? $shopId,
                    'repair_job_id' => $invoice->repair_job_id ?? $job?->id,
                    'paid_at' => $invoice->paid_at ?? $now,
                ]);
            }

            $bankTxnId = (string) ($payload['bank_tran_id'] ?? ('SBX-BNK-' . strtoupper(Str::random(8))));
            $valId = (string) ($payload['val_id'] ?? ('SBX-VAL-' . strtoupper(Str::random(8))));

            $payment->update([
                'invoice_id' => $invoice->id,
                'payment_method' => $dbMethod,
                'status' => 'PAID',
                'paid_at' => $payment->paid_at ?? $now,
                'gateway_response' => json_encode([
                    'mode' => $payload['gateway_mode'] ?? 'meramot_sandbox_escrow',
                    'channel' => $selectedChannel,
                    'wallet_masked' => $payload['wallet_masked'] ?? '017****0000',
                    'val_id' => $valId,
                    'bank_tran_id' => $bankTxnId,
                    'authorized_at' => $now->toIso8601String(),
                ]),
            ]);

            $vendorNet = round((float) $invoice->subtotal_amount, 2);
            $shopForRate = $job?->shop ?? ($shopId ? RepairShop::with('vendorProfile')->find($shopId) : null);
            $ratePct = ServicePackageController::resolveCommissionRatePct($shopForRate);
            $rateDecimal = $ratePct / 100.0;
            $commission = (float) $invoice->commission_amount > 0
                ? round((float) $invoice->commission_amount, 2)
                : round($vendorNet * $rateDecimal, 2);
            $effectiveRateDecimal = $vendorNet > 0 ? ($commission / $vendorNet) : $rateDecimal;
            $subtotalGross = round($vendorNet + $commission, 2);
            $vendorUserId = $job?->shop?->vendorProfile?->user_id
                ?? $invoice->shop?->vendorProfile?->user_id;

            $hasHold = DB::table('escrow_ledgers')
                ->where('payment_id', $payment->id)
                ->where('action', 'HOLD')
                ->exists();

            if (!$hasHold) {
                DB::table('escrow_ledgers')->insert([
                    'payment_id' => $payment->id,
                    'repair_request_id' => $req?->id,
                    'customer_user_id' => $payment->user_id,
                    'vendor_user_id' => $vendorUserId,
                    'amount' => (float) $payment->amount,
                    'gross_amount' => $subtotalGross,
                    'platform_commission_amount' => $commission,
                    'vendor_net_amount' => $vendorNet,
                    'action' => 'HOLD',
                    'note' => "Online {$selectedChannel} payment secured in Meramot Escrow ({$payment->transaction_reference})",
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $isJobAlreadyCompleted = ($req?->status === 'COMPLETED') || ($job?->status === 'COMPLETED');

            $hasCustomerLedger = FinancialLedger::where('payment_id', $payment->id)
                ->where('entry_type', 'CUSTOMER_PAYMENT')
                ->exists();

            if (!$hasCustomerLedger) {
                if ($isJobAlreadyCompleted && $shopId) {
                    AdminLedgerController::settleOrderWithCommission(
                        $vendorNet,
                        (int) $shopId,
                        (int) $payment->id,
                        (int) $invoice->id,
                        $effectiveRateDecimal,
                        "Online {$selectedChannel} settlement ({$payment->transaction_reference})"
                    );
                } else {
                    AdminLedgerController::logEntry(
                        'CUSTOMER_PAYMENT',
                        'CREDIT',
                        $subtotalGross,
                        "Online {$selectedChannel} escrow inflow received (Vendor ৳{$vendorNet} + Fee ৳{$commission}) ({$payment->transaction_reference})",
                        [
                            'shop_id' => $shopId ? (int) $shopId : null,
                            'payment_id' => (int) $payment->id,
                            'invoice_id' => (int) $invoice->id,
                        ]
                    );
                }

                $deliveryAmt = round((float) ($invoice->delivery_amount ?? 0), 2);
                $voucherCodeUpper = strtoupper((string) ($req?->voucher_code ?? ''));
                $deliveryDiscounted = in_array($voucherCodeUpper, ['FREEDELIVERY', 'SHOPFREE'], true)
                    ? min($deliveryAmt, (float) ($invoice->discount_amount ?? 0))
                    : 0.0;
                $netLogisticsInflow = max(0.0, round($deliveryAmt - $deliveryDiscounted, 2));
                if ($netLogisticsInflow > 0.009) {
                    AdminLedgerController::logEntry(
                        'DELIVERY_FEE',
                        'CREDIT',
                        $netLogisticsInflow,
                        "Platform pickup & delivery logistics fee collected via {$selectedChannel} ({$payment->transaction_reference})",
                        [
                            'shop_id' => $shopId ? (int) $shopId : null,
                            'payment_id' => (int) $payment->id,
                            'invoice_id' => (int) $invoice->id,
                        ]
                    );
                }
            }

            if ($isJobAlreadyCompleted) {
                $hasRelease = DB::table('escrow_ledgers')
                    ->where('payment_id', $payment->id)
                    ->where('action', 'RELEASE')
                    ->exists();

                if (!$hasRelease) {
                    DB::table('escrow_ledgers')->insert([
                        'payment_id' => $payment->id,
                        'repair_request_id' => $req?->id,
                        'customer_user_id' => $payment->user_id,
                        'vendor_user_id' => $vendorUserId,
                        'amount' => (float) $payment->amount,
                        'gross_amount' => $subtotalGross,
                        'platform_commission_amount' => $commission,
                        'vendor_net_amount' => $vendorNet,
                        'action' => 'RELEASE',
                        'note' => "Escrow released for completed Repair Request #{$req?->id}",
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            AuditLog::create([
                'actor_id' => auth()->id() ?? $payment->user_id,
                'action' => 'online_escrow_payment_authorized',
                'target_resource' => "payment_transactions:{$payment->id}",
                'payload_before' => ['status' => 'PENDING'],
                'payload_after' => [
                    'status' => 'PAID',
                    'channel' => $selectedChannel,
                    'amount' => (float) $payment->amount,
                    'invoice_number' => $invoice->invoice_number,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return $invoice;
        });

        if (!$wasAlreadyPaid && $payment->repairRequest?->customer) {
            try {
                $notifier->notifyUser(
                    user: $payment->repairRequest->customer,
                    eventType: 'PAYMENT_CONFIRMED',
                    title: "Payment Confirmed ({$invoice->invoice_number})",
                    message: "Your payment of BDT " . number_format((float) $payment->amount, 2) . " ({$payment->transaction_reference}) is held safely by Meramot until your repair is complete.",
                    actionUrl: "/invoices/{$invoice->id}",
                    repairRequestId: $payment->repair_request_id,
                    severity: 'success',
                    sendEmail: true,
                    sendSms: true
                );
            } catch (\Throwable $e) {
                // Non-blocking notification dispatch
            }
        }

        return $invoice;
    }

    protected function authorizeInvoiceViewer(Request $request, Invoice $invoice): void
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'Please sign in to view this official invoice.');
        }

        if ($user->role === 'admin') {
            return;
        }

        $customerId = (int) ($invoice->repairRequest?->customer_id ?? 0);
        if ($customerId > 0 && (int) $user->id === $customerId) {
            return;
        }

        $vendorUserId = (int) (
            $invoice->shop?->vendorProfile?->user_id
            ?? $invoice->repairJob?->shop?->vendorProfile?->user_id
            ?? $invoice->repairRequest?->requestedShop?->vendorProfile?->user_id
            ?? 0
        );

        if ($vendorUserId > 0 && (int) $user->id === $vendorUserId) {
            return;
        }

        abort(403, 'You do not have permission to access this invoice.');
    }
}
