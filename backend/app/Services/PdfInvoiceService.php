<?php

namespace App\Services;

use App\Http\Controllers\ServicePackageController;
use App\Models\Invoice;

/**
 * ============================================================================
 * Feature R17: Pure-PHP Official PDF Invoice Generator (%PDF-1.4)
 * ============================================================================
 * Assigned Developer: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Generates standards-compliant PDF 1.4 documents without external native OS
 * binaries so PDF receipts work identically on Windows, Linux CI, and Render.
 * ============================================================================
 */
class PdfInvoiceService
{
    public function generate(Invoice $invoice): string
    {
        $invoice->loadMissing([
            'lineItems',
            'shop.vendorProfile',
            'repairRequest.customer',
            'repairRequest.requestedShop.vendorProfile',
            'repairRequest.repairJob.shop.vendorProfile',
            'repairRequest.repairJob.warranty',
            'repairJob.shop.vendorProfile',
            'repairJob.warranty',
            'paymentTransactions',
        ]);

        $req = $invoice->repairRequest;
        $customer = $req?->customer;
        $shop = $invoice->shop ?? $invoice->repairJob?->shop ?? $req?->repairJob?->shop ?? $req?->requestedShop;
        $paidTxn = $invoice->paymentTransactions
            ->where('status', 'PAID')
            ->sortByDesc('id')
            ->first() ?? $invoice->paymentTransactions->sortByDesc('id')->first();
        $warranty = $invoice->repairJob?->warranty ?? $req?->repairJob?->warranty;

        $contentOps = [];

        // Header banner (Meramot Forest Green #214c34 -> RGB 0.129 0.298 0.204)
        $contentOps[] = '0.129 0.298 0.204 rg 36 730 540 68 re f';
        $contentOps[] = $this->pdfText(52, 772, 'F2', 18, 'MERAMOT MARKETPLACE - OFFICIAL INVOICE', [1, 1, 1]);
        $contentOps[] = $this->pdfText(52, 752, 'F1', 10, 'Verified Electronics Repair, Escrow Protection & Doorstep Logistics (Bangladesh)', [0.89, 0.98, 0.84]);
        $contentOps[] = $this->pdfText(405, 772, 'F2', 10.5, 'INVOICE: ' . ($invoice->invoice_number ?? ('INV-' . $invoice->id)), [1, 1, 1]);
        $contentOps[] = $this->pdfText(405, 754, 'F2', 10, 'STATUS: ' . strtoupper((string) $invoice->status), [0.89, 0.98, 0.84]);

        // Metadata boxes
        $contentOps[] = '0.933 0.961 0.918 rg 36 638 262 78 re f';
        $contentOps[] = '0.933 0.961 0.918 rg 314 638 262 78 re f';

        $customerName = $this->cleanText($customer->name ?? 'Valued Customer');
        $customerEmail = $this->cleanText($customer->email ?? 'N/A');
        $customerPhone = $this->cleanText($customer->phone ?? ($req->contact_phone ?? 'N/A'));
        $issuedDate = ($invoice->issued_at ?? $invoice->created_at ?? now())->format('d M Y, h:i A');

        $contentOps[] = $this->pdfText(48, 698, 'F2', 9, 'BILLED TO CUSTOMER', [0.129, 0.298, 0.204]);
        $contentOps[] = $this->pdfText(48, 683, 'F2', 11, $customerName, [0.09, 0.21, 0.15]);
        $contentOps[] = $this->pdfText(48, 668, 'F1', 9, 'Email: ' . $customerEmail, [0.25, 0.33, 0.28]);
        $contentOps[] = $this->pdfText(48, 654, 'F1', 9, 'Phone: ' . $customerPhone, [0.25, 0.33, 0.28]);

        $shopName = $this->cleanText($shop->name ?? 'Meramot Partner Workshop');
        $orderCode = $this->cleanText('MRM-REQ-' . str_pad((string) ($invoice->repair_request_id ?? $invoice->id), 4, '0', STR_PAD_LEFT));
        $deviceLabel = $this->cleanText(trim(($req->brand ?? '') . ' ' . ($req->model_name ?? $req->device_type ?? 'Device')));

        $contentOps[] = $this->pdfText(326, 698, 'F2', 9, 'WORKSHOP & ORDER DETAILS', [0.129, 0.298, 0.204]);
        $contentOps[] = $this->pdfText(326, 683, 'F2', 11, $shopName, [0.09, 0.21, 0.15]);
        $contentOps[] = $this->pdfText(326, 668, 'F1', 9, 'Order Ref: ' . $orderCode . ' | Device: ' . mb_substr($deviceLabel, 0, 26), [0.25, 0.33, 0.28]);
        $contentOps[] = $this->pdfText(326, 654, 'F1', 9, 'Issued At: ' . $issuedDate, [0.25, 0.33, 0.28]);

        // Table Header
        $y = 606;
        $contentOps[] = '0.129 0.298 0.204 rg 36 ' . ($y - 6) . ' 540 24 re f';
        $contentOps[] = $this->pdfText(46, $y + 2, 'F2', 9, 'ITEM / SERVICE TITLE', [1, 1, 1]);
        $contentOps[] = $this->pdfText(260, $y + 2, 'F2', 9, 'SPECIFICATION', [1, 1, 1]);
        $contentOps[] = $this->pdfText(415, $y + 2, 'F2', 9, 'QTY', [1, 1, 1]);
        $contentOps[] = $this->pdfText(455, $y + 2, 'F2', 9, 'UNIT (BDT)', [1, 1, 1]);
        $contentOps[] = $this->pdfText(522, $y + 2, 'F2', 9, 'TOTAL', [1, 1, 1]);

        $y -= 24;
        $items = $invoice->lineItems;
        if ($items->isEmpty()) {
            $fallbackSubtotal = (float) ($invoice->subtotal_amount ?? 0);
            $items = collect([
                (object) [
                    'title' => 'Verified Repair & Diagnostic Service',
                    'description' => $orderCode,
                    'quantity' => 1,
                    'unit_price' => $fallbackSubtotal,
                    'total_price' => $fallbackSubtotal,
                ],
            ]);
        }

        foreach ($items->take(10) as $idx => $item) {
            if ($idx % 2 === 1) {
                $contentOps[] = '0.965 0.980 0.957 rg 36 ' . ($y - 6) . ' 540 20 re f';
            }
            $title = mb_substr($this->cleanText((string) ($item->title ?? $item->description ?? 'Repair Line Item')), 0, 38);
            $spec = mb_substr($this->cleanText((string) ($item->description ?? 'Workshop bench service')), 0, 28);
            $qty = (int) ($item->quantity ?? 1);
            $unit = number_format((float) ($item->unit_price ?? 0), 2);
            $tot = number_format((float) ($item->total_price ?? 0), 2);

            $contentOps[] = $this->pdfText(46, $y, 'F1', 9, $title, [0.1, 0.15, 0.12]);
            $contentOps[] = $this->pdfText(260, $y, 'F1', 8, $spec, [0.3, 0.38, 0.33]);
            $contentOps[] = $this->pdfText(420, $y, 'F1', 9, (string) $qty, [0.1, 0.15, 0.12]);
            $contentOps[] = $this->pdfText(455, $y, 'F1', 9, $unit, [0.1, 0.15, 0.12]);
            $contentOps[] = $this->pdfText(518, $y, 'F2', 9, $tot, [0.1, 0.15, 0.12]);
            $y -= 20;
        }

        // Totals Summary Box
        $y -= 12;
        $contentOps[] = '0.82 0.88 0.80 RG 1 w 36 ' . ($y + 10) . ' m 576 ' . ($y + 10) . ' l S';

        $subtotal = (float) ($invoice->subtotal_amount ?? 0);
        $commissionAmt = (float) ($invoice->commission_amount ?? 0);
        $delivery = (float) ($invoice->delivery_amount ?? 0);
        $discount = (float) ($invoice->discount_amount ?? 0);
        $rateLabelPct = $subtotal > 0 && $commissionAmt > 0
            ? rtrim(rtrim(number_format(($commissionAmt / $subtotal) * 100, 2), '0'), '.')
            : rtrim(rtrim(number_format(ServicePackageController::resolveCommissionRatePct($shop), 2), '0'), '.');
        $grandTotal = (float) ($invoice->total_amount ?? max(0.0, $subtotal + $commissionAmt + $delivery - $discount));

        $summaryRows = [
            ['Vendor Services & Parts (100% Vendor):', 'BDT ' . number_format($subtotal, 2)],
            ['Meramot Platform Fee (' . $rateLabelPct . '%, Excl. Delivery):', 'BDT ' . number_format($commissionAmt, 2)],
            ['Platform Pickup & Delivery Logistics:', 'BDT ' . number_format($delivery, 2)],
        ];
        if ($discount > 0) {
            $voucherSuffix = !empty($req?->voucher_code) ? ' (' . $this->cleanText($req->voucher_code) . ')' : '';
            $summaryRows[] = ['Promo / Voucher Discount' . $voucherSuffix . ':', '- BDT ' . number_format($discount, 2)];
        }

        foreach ($summaryRows as [$label, $val]) {
            $contentOps[] = $this->pdfText(320, $y - 6, 'F1', 9.0, $label, [0.22, 0.30, 0.25]);
            $contentOps[] = $this->pdfText(482, $y - 6, 'F2', 9.5, $val, [0.10, 0.18, 0.13]);
            $y -= 17;
        }

        $contentOps[] = '0.129 0.298 0.204 rg 320 ' . ($y - 14) . ' 256 26 re f';
        $contentOps[] = $this->pdfText(332, $y - 5, 'F2', 11, 'GRAND TOTAL (BDT):', [1, 1, 1]);
        $contentOps[] = $this->pdfText(472, $y - 5, 'F2', 11, 'BDT ' . number_format($grandTotal, 2), [0.89, 0.98, 0.84]);

        // Payment, Escrow & Warranty Governance Footer
        $y -= 52;
        $contentOps[] = '0.933 0.961 0.918 rg 36 ' . ($y - 54) . ' 540 72 re f';

        $payMethod = $paidTxn ? strtoupper((string) $paidTxn->payment_method) : 'PENDING SETTLEMENT';
        $txnRef = $paidTxn ? $this->cleanText((string) ($paidTxn->transaction_reference ?: ('TXN-' . $paidTxn->id))) : 'N/A';
        $commissionNote = 'Pricing Policy: 100% Listed Price goes to Workshop (BDT ' . number_format($subtotal, 2) . ') | ' . $rateLabelPct . '% Platform Fee (BDT ' . number_format($commissionAmt, 2) . ') excl. Platform Logistics.';
        $warrantyNote = $warranty
            ? ('Active Warranty: ' . (int) $warranty->duration_days . ' days coverage (Ends: ' . optional($warranty->ends_at)->format('d M Y') . '). ' . mb_substr($this->cleanText((string) ($warranty->details ?: $warranty->title)), 0, 68))
            : 'Warranty Protection: Activated automatically upon workshop completion and customer handover verification.';

        $contentOps[] = $this->pdfText(48, $y + 2, 'F2', 9.5, 'PAYMENT & ESCROW AUDIT RECORD', [0.129, 0.298, 0.204]);
        $contentOps[] = $this->pdfText(48, $y - 13, 'F1', 8.5, 'Payment Channel: ' . $payMethod . '   |   Transaction Reference: ' . $txnRef, [0.15, 0.22, 0.18]);
        $contentOps[] = $this->pdfText(48, $y - 27, 'F1', 8.5, $commissionNote, [0.22, 0.30, 0.25]);
        $contentOps[] = $this->pdfText(48, $y - 41, 'F1', 8.5, $warrantyNote, [0.22, 0.30, 0.25]);

        $contentOps[] = $this->pdfText(36, 42, 'F1', 8, 'Generated electronically by Meramot Trust & Financial Ledger Engine. Valid without physical signature.', [0.42, 0.48, 0.44]);

        $stream = implode("\n", $contentOps);

        return $this->assembleSinglePagePdf($stream);
    }

    protected function cleanText(string $text): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $clean = $ascii !== false ? $ascii : preg_replace('/[^\x20-\x7E]/', '', $text);
        $clean = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $clean);
        return trim(preg_replace('/\s+/', ' ', $clean) ?? '');
    }

    protected function pdfText(float $x, float $y, string $font, float $size, string $text, array $rgb = [0, 0, 0]): string
    {
        $escaped = $this->cleanText($text);
        $r = number_format($rgb[0], 3, '.', '');
        $g = number_format($rgb[1], 3, '.', '');
        $b = number_format($rgb[2], 3, '.', '');

        return "BT /{$font} {$size} Tf {$r} {$g} {$b} rg {$x} {$y} Td ({$escaped}) Tj ET";
    }

    protected function assembleSinglePagePdf(string $stream): string
    {
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
        $objects[6] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }
}
