<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\RepairRequest;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R17: SSLCommerz/Stripe Online Payment Gateway & PDF Invoice
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
 * Architectural Layer: Controller (PaymentGatewayController)
 * Models Touched: PaymentTransaction, Invoice
 * View Rendered: resources/views/checkout/payment.blade.php
 *
 * INSTRUCTIONS FOR NAWSHAD:
 * 1. Implement initiatePayment($requestId): Generate unique transaction ID,
 *    calculate total bill including 10% platform fee, and dispatch SSLCommerz request.
 * 2. Implement handleCallback(Request $request): Validate SSLCommerz IPN hash,
 *    mark payment status as 'successful', and generate customer PDF invoice.
 * ============================================================================
 */
class PaymentGatewayController extends Controller
{
    /**
     * Show payment checkout screen.
     * Route: GET /checkout/payment/{requestId}
     */
    public function showCheckout($requestId)
    {
        $repairRequest = RepairRequest::with('bids')->findOrFail($requestId);
        return view('checkout.payment', compact('repairRequest'));
    }

    /**
     * Initiate payment transaction with SSLCommerz sandbox.
     * Route: POST /payment/initiate
     */
    public function initiatePayment(Request $request)
    {
        $validated = $request->validate([
            'repair_request_id' => 'required|exists:repair_requests,id',
            'amount' => 'required|numeric|min:10',
        ]);

        $trxId = 'MRM_' . uniqid();

        $transaction = PaymentTransaction::create([
            'transaction_id' => $trxId,
            'repair_request_id' => $validated['repair_request_id'],
            'customer_id' => $request->user()->id ?? 1,
            'amount' => $validated['amount'],
            'gateway' => 'sslcommerz',
            'payment_status' => 'pending',
            'invoice_number' => 'INV-' . date('Ymd') . '-' . rand(1000, 9999),
        ]);

        // TODO (Nawshad): Integrate SSLCommerz Session initiation and redirect user to Hosted payment page

        return redirect()->route('payment.success', ['trx_id' => $trxId]);
    }

    /**
     * SSLCommerz success / IPN callback handler.
     * Route: POST /payment/success
     */
    public function handleSuccess(Request $request)
    {
        // TODO (Nawshad): Validate IPN hash parameters and update PaymentTransaction status = 'successful'
        return view('checkout.success');
    }
}
