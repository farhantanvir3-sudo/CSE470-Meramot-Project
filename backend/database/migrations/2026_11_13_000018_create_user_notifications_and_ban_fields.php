<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'ban_reason')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('ban_reason')->nullable()->after('status');
            });
        }

        if (!Schema::hasColumn('users', 'banned_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('banned_at')->nullable()->after('ban_reason');
            });
        }

        if (!Schema::hasTable('user_notifications')) {
            Schema::create('user_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 60)->default('MODERATION_ALERT');
                $table->string('severity', 20)->default('info');
                $table->string('title');
                $table->text('message');
                $table->string('action_url')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('popup_shown_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // Live DB Cleanup & Realistic Offensive-Review Demo Seed
        // Only runs when demo seed data already exists (leaves empty RefreshDatabase PHPUnit runs untouched)
        $hasAdminDemo = DB::table('users')->where('email', 'admin@meramot.demo')->exists();
        $firstShop = DB::table('repair_shops')
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('id')
            ->first();

        if ($hasAdminDemo && $firstShop) {
            $adminIds = DB::table('users')->where('role', 'admin')->pluck('id')->all();

            if (!empty($adminIds)) {
                $affectedShopIds = DB::table('shop_reviews')
                    ->whereIn('customer_id', $adminIds)
                    ->pluck('repair_shop_id')
                    ->unique()
                    ->values()
                    ->all();

                DB::table('shop_reviews')->whereIn('customer_id', $adminIds)->delete();

                // Delete any repair_requests accidentally created under an admin account that lack payment rows
                DB::table('repair_requests')
                    ->whereIn('customer_id', $adminIds)
                    ->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('payment_transactions')
                            ->whereColumn('payment_transactions.repair_request_id', 'repair_requests.id');
                    })
                    ->delete();

                foreach ($affectedShopIds as $shopId) {
                    $stats = DB::table('shop_reviews')
                        ->where('repair_shop_id', $shopId)
                        ->where('is_hidden', false)
                        ->selectRaw('COUNT(*) as cnt, COALESCE(AVG(rating), 0) as avg_rating')
                        ->first();
                    DB::table('repair_shops')->where('id', $shopId)->update([
                        'review_count' => (int) ($stats->cnt ?? 0),
                        'rating' => $stats && (int) $stats->cnt > 0 ? round((float) $stats->avg_rating, 2) : 0.00,
                    ]);
                }
            }

            $existingKamrul = DB::table('users')->where('email', 'kamrul.reviewer@meramot.demo')->first();
            if (!$existingKamrul) {
                $now = now();
                $kamrulId = DB::table('users')->insertGetId([
                    'username' => 'kamrul_reviewer',
                    'name' => 'Kamrul Islam',
                    'email' => 'kamrul.reviewer@meramot.demo',
                    'password' => Hash::make('password123'),
                    'phone' => '+8801718990011',
                    'address' => 'House 19, Road 4, Banani, Dhaka',
                    'city' => 'Dhaka',
                    'area' => 'Banani',
                    'role' => 'customer',
                    'is_email_verified' => true,
                    'is_phone_verified' => true,
                    'status' => 'active',
                    'is_active' => true,
                    'created_at' => $now->copy()->subDays(12),
                    'updated_at' => $now->copy()->subDays(12),
                ]);

                // 1. Completed Past Order with full Warranty -> Invoice -> Payment -> Escrow -> Ledger chain
                $completedReqId = DB::table('repair_requests')->insertGetId([
                    'customer_id' => $kamrulId,
                    'source' => 'MARKETPLACE',
                    'requested_shop_id' => $firstShop->id,
                    'title' => 'MacBook Air M1 Battery Replacement & Port Cleaning',
                    'description' => 'Battery degraded below 65% and USB-C charging intermittent.',
                    'problem' => 'Battery degraded below 65% and USB-C charging intermittent.',
                    'device_type' => 'Laptop',
                    'brand' => 'Apple',
                    'model_name' => 'MacBook Air M1',
                    'issue_category' => 'Battery Replacement',
                    'mode' => 'CHECKUP_AND_REPAIR',
                    'status' => 'COMPLETED',
                    'preferred_pickup' => false,
                    'preferred_service_type' => 'walk_in',
                    'contact_phone' => '+8801718990011',
                    'pickup_address' => 'House 19, Road 4, Banani, Dhaka',
                    'dropoff_address' => $firstShop->address ?? ($firstShop->area . ', ' . ($firstShop->city ?? 'Dhaka')),
                    'quoted_final_amount' => 4500.00,
                    'created_at' => $now->copy()->subDays(9),
                    'updated_at' => $now->copy()->subDays(6),
                ]);

                $jobId = DB::table('repair_jobs')->insertGetId([
                    'repair_request_id' => $completedReqId,
                    'shop_id' => $firstShop->id,
                    'status' => 'COMPLETED',
                    'final_quoted_amount' => 4500.00,
                    'started_at' => $now->copy()->subDays(8),
                    'completed_at' => $now->copy()->subDays(6),
                    'created_at' => $now->copy()->subDays(8),
                    'updated_at' => $now->copy()->subDays(6),
                ]);

                DB::table('warranties')->insert([
                    'repair_job_id' => $jobId,
                    'title' => '90-Day Meramot Bench Repair Warranty',
                    'details' => 'Covers battery cell & charging flex alignment for 90 days.',
                    'duration_days' => 90,
                    'starts_at' => $now->copy()->subDays(6),
                    'ends_at' => $now->copy()->addDays(84),
                    'created_at' => $now->copy()->subDays(6),
                    'updated_at' => $now->copy()->subDays(6),
                ]);

                $invoiceId = DB::table('invoices')->insertGetId([
                    'repair_request_id' => $completedReqId,
                    'repair_job_id' => $jobId,
                    'shop_id' => $firstShop->id,
                    'type' => 'FINAL',
                    'status' => 'PAID',
                    'invoice_number' => 'INV-KAM-' . strtoupper(Str::random(6)),
                    'subtotal_amount' => 4500.00,
                    'delivery_amount' => 0.00,
                    'discount_amount' => 0.00,
                    'commission_amount' => 450.00,
                    'total_amount' => 4500.00,
                    'issued_at' => $now->copy()->subDays(6),
                    'paid_at' => $now->copy()->subDays(6),
                    'created_at' => $now->copy()->subDays(6),
                    'updated_at' => $now->copy()->subDays(6),
                ]);

                DB::table('invoice_line_items')->insert([
                    'invoice_id' => $invoiceId,
                    'title' => 'MacBook Air M1 Battery Replacement & Port Cleaning',
                    'description' => 'OEM Battery Cell & USB-C Cleaning Bench Work',
                    'quantity' => 1,
                    'unit_price' => 4500.00,
                    'total_price' => 4500.00,
                    'created_at' => $now->copy()->subDays(6),
                    'updated_at' => $now->copy()->subDays(6),
                ]);

                $paymentId = DB::table('payment_transactions')->insertGetId([
                    'user_id' => $kamrulId,
                    'repair_request_id' => $completedReqId,
                    'invoice_id' => $invoiceId,
                    'amount' => 4500.00,
                    'currency' => 'BDT',
                    'payment_method' => 'SSLCOMMERZ',
                    'status' => 'PAID',
                    'transaction_reference' => 'MRM-MOD-' . strtoupper(Str::random(6)),
                    'paid_at' => $now->copy()->subDays(6),
                    'created_at' => $now->copy()->subDays(6),
                    'updated_at' => $now->copy()->subDays(6),
                ]);

                $vendorUserId = DB::table('vendor_profiles')
                    ->where('id', $firstShop->vendor_profile_id)
                    ->value('user_id');

                DB::table('escrow_ledgers')->insert([
                    'payment_id' => $paymentId,
                    'repair_request_id' => $completedReqId,
                    'customer_user_id' => $kamrulId,
                    'vendor_user_id' => $vendorUserId,
                    'amount' => 4500.00,
                    'gross_amount' => 4500.00,
                    'platform_commission_amount' => 450.00,
                    'vendor_net_amount' => 4050.00,
                    'action' => 'RELEASE',
                    'note' => 'Released after completed battery replacement.',
                    'created_at' => $now->copy()->subDays(6),
                    'updated_at' => $now->copy()->subDays(6),
                ]);

                DB::table('financial_ledgers')->insert([
                    [
                        'shop_id' => $firstShop->id,
                        'payment_id' => $paymentId,
                        'invoice_id' => $invoiceId,
                        'entry_type' => 'CUSTOMER_PAYMENT',
                        'direction' => 'CREDIT',
                        'amount' => 4500.00,
                        'currency' => 'BDT',
                        'description' => "Customer escrow payment for Order #{$completedReqId} (MacBook Air M1 Battery Replacement)",
                        'effective_at' => $now->copy()->subDays(6),
                        'created_at' => $now->copy()->subDays(6),
                        'updated_at' => $now->copy()->subDays(6),
                    ],
                    [
                        'shop_id' => $firstShop->id,
                        'payment_id' => $paymentId,
                        'invoice_id' => $invoiceId,
                        'entry_type' => 'PLATFORM_COMMISSION',
                        'direction' => 'CREDIT',
                        'amount' => 450.00,
                        'currency' => 'BDT',
                        'description' => "10% platform commission for Order #{$completedReqId}",
                        'effective_at' => $now->copy()->subDays(6),
                        'created_at' => $now->copy()->subDays(6),
                        'updated_at' => $now->copy()->subDays(6),
                    ],
                    [
                        'shop_id' => $firstShop->id,
                        'payment_id' => $paymentId,
                        'invoice_id' => $invoiceId,
                        'entry_type' => 'VENDOR_EARNING',
                        'direction' => 'CREDIT',
                        'amount' => 4050.00,
                        'currency' => 'BDT',
                        'description' => "90% net workshop earning for Order #{$completedReqId}",
                        'effective_at' => $now->copy()->subDays(6),
                        'created_at' => $now->copy()->subDays(6),
                        'updated_at' => $now->copy()->subDays(6),
                    ],
                ]);

                // 2. Ongoing Order (BIDDING) so banned user can still view ongoing + past orders
                DB::table('repair_requests')->insert([
                    'customer_id' => $kamrulId,
                    'source' => 'MARKETPLACE',
                    'requested_shop_id' => null,
                    'title' => 'Sony WH-1000XM5 Hinge & ANC Mic Check',
                    'description' => 'Right earcup creaks and ANC feedback microphone clicks intermittently.',
                    'problem' => 'Right earcup creaks and ANC feedback microphone clicks intermittently.',
                    'device_type' => 'Audio',
                    'brand' => 'Sony',
                    'model_name' => 'WH-1000XM5',
                    'issue_category' => 'Hardware Diagnostics',
                    'mode' => 'CHECKUP_AND_REPAIR',
                    'status' => 'BIDDING',
                    'preferred_pickup' => true,
                    'preferred_service_type' => 'pickup_dropoff',
                    'contact_phone' => '+8801718990011',
                    'pickup_address' => 'House 19, Road 4, Banani, Dhaka',
                    'created_at' => $now->copy()->subDays(2),
                    'updated_at' => $now->copy()->subDays(2),
                ]);

                // 3. Offensive Review for Admin Moderation Demo
                DB::table('shop_reviews')->insert([
                    'repair_shop_id' => $firstShop->id,
                    'customer_id' => $kamrulId,
                    'repair_request_id' => $completedReqId,
                    'rating' => 1,
                    'service_provided' => 'Battery Replacement',
                    'review_comment' => 'Utterly pathetic service! Useless idiots and total frauds running a garbage workshop — avoid these fools!',
                    'is_verified_purchase' => true,
                    'is_hidden' => false,
                    'created_at' => $now->copy()->subDay(),
                    'updated_at' => $now->copy()->subDay(),
                ]);

                $shopStats = DB::table('shop_reviews')
                    ->where('repair_shop_id', $firstShop->id)
                    ->where('is_hidden', false)
                    ->selectRaw('COUNT(*) as cnt, COALESCE(AVG(rating), 0) as avg_rating')
                    ->first();
                DB::table('repair_shops')->where('id', $firstShop->id)->update([
                    'review_count' => (int) ($shopStats->cnt ?? 0),
                    'rating' => $shopStats && (int) $shopStats->cnt > 0 ? round((float) $shopStats->avg_rating, 2) : 0.00,
                ]);
                if ($firstShop->vendor_profile_id) {
                    $vpShopIds = DB::table('repair_shops')->where('vendor_profile_id', $firstShop->vendor_profile_id)->pluck('id')->all();
                    $vpAvg = DB::table('shop_reviews')
                        ->whereIn('repair_shop_id', $vpShopIds)
                        ->where('is_hidden', false)
                        ->avg('rating');
                    DB::table('vendor_profiles')->where('id', $firstShop->vendor_profile_id)->update([
                        'rating' => $vpAvg ? round((float) $vpAvg, 2) : 0.00,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');

        if (Schema::hasColumn('users', 'banned_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('banned_at');
            });
        }
        if (Schema::hasColumn('users', 'ban_reason')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('ban_reason');
            });
        }
    }
};
