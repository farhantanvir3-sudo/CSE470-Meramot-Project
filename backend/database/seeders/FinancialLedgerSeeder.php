<?php

namespace Database\Seeders;

use App\Http\Controllers\AdminLedgerController;
use App\Models\FinancialLedger;
use App\Models\RepairShop;
use Illuminate\Database\Seeder;

/**
 * ============================================================================
 * FEATURE R16: Baseline Double-Entry Financial Ledger Seeder
 * ============================================================================
 * Author: Md. Nawshad Zaman Khan [Student ID: 23301622] (Commit 4742c9c)
 * Demonstrates 10% platform commission accounting and cumulative credits/debits.
 * ============================================================================
 */
class FinancialLedgerSeeder extends Seeder
{
    /**
     * Seed baseline double-entry ledger transactions for Feature R16.
     * Demonstrates 10% platform commission accounting and cumulative credits vs debits.
     */
    public function run(): void
    {
        // If already seeded, skip to preserve immutability
        if (FinancialLedger::count() > 0) {
            return;
        }

        $shops = RepairShop::take(5)->get();
        if ($shops->isEmpty()) {
            return;
        }

        $sampleOrders = [
            ['gross' => 4500.00, 'desc' => 'iPhone 13 OLED Screen Replacement', 'shop' => $shops[0] ?? null],
            ['gross' => 2800.00, 'desc' => 'Samsung S22 Ultra Battery Replacement', 'shop' => $shops[1] ?? $shops[0]],
            ['gross' => 8500.00, 'desc' => 'MacBook Pro M1 Thermal Repasting & Fan Repair', 'shop' => $shops[2] ?? $shops[0]],
            ['gross' => 1200.00, 'desc' => 'Sony WH-1000XM4 Headband Hinge Fix', 'shop' => $shops[3] ?? $shops[0]],
            ['gross' => 3400.00, 'desc' => 'OnePlus 9 Pro Charging Port Replacement', 'shop' => $shops[0] ?? null],
            ['gross' => 6200.00, 'desc' => 'Dell XPS 13 Keyboard & Trackpad Repair', 'shop' => $shops[1] ?? $shops[0]],
        ];

        foreach ($sampleOrders as $order) {
            $gross = $order['gross'];
            $shop = $order['shop'];
            $shopId = $shop ? $shop->id : 1;

            // 1. Customer Payment (CREDIT total gross)
            FinancialLedger::create([
                'shop_id'      => $shopId,
                'entry_type'   => 'CUSTOMER_PAYMENT',
                'direction'    => 'CREDIT',
                'amount'       => $gross,
                'currency'     => 'BDT',
                'description'  => "Customer payment received: {$order['desc']}",
                'effective_at' => now()->subDays(rand(2, 10)),
            ]);

            // 2. 10% Platform Commission (CREDIT platform commission)
            $commission = round($gross * 0.10, 2);
            FinancialLedger::create([
                'shop_id'      => $shopId,
                'entry_type'   => 'PLATFORM_COMMISSION',
                'direction'    => 'CREDIT',
                'amount'       => $commission,
                'currency'     => 'BDT',
                'description'  => "10% platform commission deducted for order (Gross: ৳{$gross})",
                'effective_at' => now()->subDays(rand(2, 10)),
            ]);

            // 3. Vendor Net Earning (DEBIT from platform pool / allocated to vendor escrow)
            $vendorNet = round($gross - $commission, 2);
            FinancialLedger::create([
                'shop_id'      => $shopId,
                'entry_type'   => 'VENDOR_EARNING',
                'direction'    => 'DEBIT',
                'amount'       => $vendorNet,
                'currency'     => 'BDT',
                'description'  => "Net earning allocated to {$shop->name} (90% of ৳{$gross})",
                'effective_at' => now()->subDays(rand(2, 10)),
            ]);

            // 4. Logistics delivery fee (CREDIT) and payout (DEBIT)
            FinancialLedger::create([
                'shop_id'      => $shopId,
                'entry_type'   => 'DELIVERY_FEE',
                'direction'    => 'CREDIT',
                'amount'       => 150.00,
                'currency'     => 'BDT',
                'description'  => "Doorstep logistics delivery fee charged to customer",
                'effective_at' => now()->subDays(rand(1, 5)),
            ]);

            FinancialLedger::create([
                'shop_id'      => $shopId,
                'entry_type'   => 'DELIVERY_PAYOUT',
                'direction'    => 'DEBIT',
                'amount'       => 150.00,
                'currency'     => 'BDT',
                'description'  => "Delivery fee disbursed to assigned rider",
                'effective_at' => now()->subDays(rand(1, 5)),
            ]);
        }

        // Add one sample vendor payout disbursement
        FinancialLedger::create([
            'shop_id'      => $shops[0]->id,
            'entry_type'   => 'VENDOR_PAYOUT',
            'direction'    => 'DEBIT',
            'amount'       => 5000.00,
            'currency'     => 'BDT',
            'description'  => "Weekly automated bank payout disbursed to {$shops[0]->name}",
            'effective_at' => now()->subDay(),
        ]);
    }
}

