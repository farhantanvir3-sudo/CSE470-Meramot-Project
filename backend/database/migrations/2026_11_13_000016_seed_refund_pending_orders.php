<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('repair_shops')
            || !Schema::hasTable('users')
            || !Schema::hasTable('dispute_cases')
            || !Schema::hasTable('payment_transactions')
            || !Schema::hasTable('financial_ledgers')
        ) {
            return;
        }

        // Only backfill when running against a seeded database (e.g. production Render/Neon)
        $customer = DB::table('users')->where('role', 'customer')->orderBy('id')->first();
        $admin = DB::table('users')->where('role', 'admin')->orderBy('id')->first();
        $shops = DB::table('repair_shops')
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('id')
            ->take(2)
            ->get();

        if (!$customer || !$admin || $shops->isEmpty()) {
            return;
        }

        $cases = [
            [
                'invoice_number' => 'INV-2026-RFD-PEND01',
                'trx_ref' => 'TRX-MRM-RFDPEND01',
                'shop' => $shops[0],
                'title' => 'Samsung Galaxy S23 Ultra — Faulty Touch Digitizer After Settlement',
                'device_type' => 'Phone',
                'brand' => 'Samsung',
                'model_name' => 'Galaxy S23 Ultra',
                'issue_category' => 'Screen Issue',
                'problem' => 'Touch digitizer unresponsive along left edge immediately after pickup & full settlement.',
                'amount' => 4200.00,
                'method' => 'BKASH',
                'dispute_status' => 'OPEN',
                'note' => 'Customer uploaded video proof of dead touch zone 2 hours after settlement; full refund requested.',
            ],
            [
                'invoice_number' => 'INV-2026-RFD-PEND02',
                'trx_ref' => 'TRX-MRM-RFDPEND02',
                'shop' => $shops->count() > 1 ? $shops[1] : $shops[0],
                'title' => 'MacBook Pro 14" — Swollen Replacement Battery Within 48 Hours',
                'device_type' => 'Laptop',
                'brand' => 'Apple',
                'model_name' => 'MacBook Pro 14 M2',
                'issue_category' => 'Battery Drain',
                'problem' => 'Replacement battery swelled and trackpad stopped clicking 48 hours after order settlement.',
                'amount' => 6500.00,
                'method' => 'NAGAD',
                'dispute_status' => 'UNDER_REVIEW',
                'note' => 'Vendor did not respond to 24-hour warranty replacement notice; escalated for Admin refund.',
            ],
        ];

        foreach ($cases as $idx => $c) {
            $alreadyExists = DB::table('invoices')
                ->where('invoice_number', $c['invoice_number'])
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $shop = $c['shop'];
            $vendorProfile = $shop->vendor_profile_id
                ? DB::table('vendor_profiles')->where('id', $shop->vendor_profile_id)->first()
                : null;
            $vendorUserId = $vendorProfile?->user_id ?? $admin->id;

            $t = now()->subDays(2 - $idx);
            $gross = $c['amount'];
            $commission = round($gross * 0.10, 2);
            $vendorNet = round($gross - $commission, 2);

            $reqId = DB::table('repair_requests')->insertGetId([
                'customer_id' => $customer->id,
                'source' => 'MARKETPLACE',
                'requested_shop_id' => $shop->id,
                'title' => $c['title'],
                'description' => $c['problem'],
                'device_type' => $c['device_type'],
                'brand' => $c['brand'],
                'model_name' => $c['model_name'],
                'issue_category' => $c['issue_category'],
                'problem' => $c['problem'],
                'mode' => 'FINAL_QUOTE',
                'status' => 'COMPLETED',
                'preferred_pickup' => false,
                'preferred_service_type' => 'walk_in',
                'contact_phone' => $customer->phone ?? '+8801700000000',
                'dropoff_address' => $shop->address ?? 'Dhaka',
                'checkup_fee' => 300.00,
                'quoted_final_amount' => $gross,
                'created_at' => $t,
                'updated_at' => $t,
            ]);

            $jobId = DB::table('repair_jobs')->insertGetId([
                'repair_request_id' => $reqId,
                'shop_id' => $shop->id,
                'status' => 'COMPLETED',
                'diagnosis_notes' => 'Settled repair job flagged with post-delivery vendor quality dispute.',
                'final_quoted_amount' => $gross,
                'customer_approved' => true,
                'started_at' => $t,
                'completed_at' => $t,
                'created_at' => $t,
                'updated_at' => $t,
            ]);

            $invoiceId = DB::table('invoices')->insertGetId([
                'repair_request_id' => $reqId,
                'repair_job_id' => $jobId,
                'shop_id' => $shop->id,
                'type' => 'FINAL',
                'status' => 'PAID',
                'invoice_number' => $c['invoice_number'],
                'subtotal_amount' => $gross,
                'delivery_amount' => 0.00,
                'discount_amount' => 0.00,
                'commission_amount' => $commission,
                'total_amount' => $gross,
                'issued_at' => $t,
                'due_at' => $t,
                'paid_at' => $t,
                'created_at' => $t,
                'updated_at' => $t,
            ]);

            $paymentId = DB::table('payment_transactions')->insertGetId([
                'user_id' => $customer->id,
                'repair_request_id' => $reqId,
                'invoice_id' => $invoiceId,
                'amount' => $gross,
                'currency' => 'BDT',
                'payment_method' => $c['method'],
                'status' => 'PAID',
                'transaction_reference' => $c['trx_ref'],
                'gateway_response' => json_encode(['status' => 'PAID', 'invoice' => $c['invoice_number']]),
                'paid_at' => $t,
                'created_at' => $t,
                'updated_at' => $t,
            ]);

            $disputeId = DB::table('dispute_cases')->insertGetId([
                'repair_request_id' => $reqId,
                'repair_job_id' => $jobId,
                'payment_id' => $paymentId,
                'opened_by_id' => $customer->id,
                'against_id' => $vendorUserId,
                'assigned_admin_id' => $admin->id,
                'status' => $c['dispute_status'],
                'resolution' => $c['problem'],
                'created_at' => $t,
                'updated_at' => $t,
            ]);

            DB::table('dispute_notes')->insert([
                'dispute_case_id' => $disputeId,
                'author_id' => $customer->id,
                'note' => $c['note'],
                'is_internal' => false,
                'created_at' => $t,
                'updated_at' => $t,
            ]);

            // Post original settled double-entry rows into financial_ledgers
            DB::table('financial_ledgers')->insert([
                [
                    'shop_id' => $shop->id,
                    'payment_id' => $paymentId,
                    'invoice_id' => $invoiceId,
                    'entry_type' => 'CUSTOMER_PAYMENT',
                    'direction' => 'CREDIT',
                    'amount' => $gross,
                    'currency' => 'BDT',
                    'description' => "Settled customer payment ({$c['invoice_number']}) — Disputed post-delivery",
                    'effective_at' => $t,
                    'created_at' => $t,
                    'updated_at' => $t,
                ],
                [
                    'shop_id' => $shop->id,
                    'payment_id' => $paymentId,
                    'invoice_id' => $invoiceId,
                    'entry_type' => 'PLATFORM_COMMISSION',
                    'direction' => 'CREDIT',
                    'amount' => $commission,
                    'currency' => 'BDT',
                    'description' => "10% platform commission ({$c['invoice_number']})",
                    'effective_at' => $t,
                    'created_at' => $t,
                    'updated_at' => $t,
                ],
                [
                    'shop_id' => $shop->id,
                    'payment_id' => $paymentId,
                    'invoice_id' => $invoiceId,
                    'entry_type' => 'VENDOR_EARNING',
                    'direction' => 'DEBIT',
                    'amount' => $vendorNet,
                    'currency' => 'BDT',
                    'description' => "90% vendor escrow allocation ({$c['invoice_number']}) — Refund pending",
                    'effective_at' => $t,
                    'created_at' => $t,
                    'updated_at' => $t,
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive seed migration
    }
};
