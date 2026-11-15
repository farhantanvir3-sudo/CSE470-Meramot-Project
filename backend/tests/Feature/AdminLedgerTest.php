<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminLedgerController;
use App\Models\FinancialLedger;
use App\Models\RepairShop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ============================================================================
 * FEATURE R16: Platform Financial Summary & Double-Entry Ledger Tests
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622] (Commit 4742c9c)
 * Merged BI & Settlement Controls: Farhan Tanvir [Student ID: 22299179]
 * ============================================================================
 */
class AdminLedgerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_displays_financial_overview_and_calculates_double_entry_totals()
    {
        $shop = RepairShop::factory()->create(['name' => 'ProTech Solutions']);

        AdminLedgerController::settleOrderWithCommission(4500.00, $shop->id);

        $response = $this->get('/admin/financial-overview');

        $response->assertStatus(200);
        $response->assertSee('Financial Business Intelligence & Double-Entry Ledger', false);
        $response->assertSee('ProTech Solutions');
        $response->assertSee('4,860.00');
        $response->assertSee('360.00');
        $response->assertSee('4,500.00');
        $response->assertSee('Platform Income Rate Graph');
        $response->assertSee('Best Performing Shops');
    }

    /** @test */
    public function it_supports_postgresql_safe_search_and_filters_on_ledger()
    {
        $shop = RepairShop::factory()->create(['name' => 'MacMaster Repairs']);

        AdminLedgerController::logEntry(
            'CUSTOMER_PAYMENT',
            'CREDIT',
            3000.00,
            'MacBook logic board settlement',
            ['shop_id' => $shop->id]
        );

        // Search by text string (tests PostgreSQL CAST(id AS TEXT) ILIKE without crashing on bigint)
        $response = $this->get('/admin/financial-overview?search=MacBook&direction=CREDIT&entry_type=CUSTOMER_PAYMENT&shop_id=' . $shop->id);

        $response->assertStatus(200);
        $response->assertSee('MacBook logic board settlement');
        $response->assertSee('MacMaster Repairs');
    }

    /** @test */
    public function it_enforces_immutability_on_financial_ledger_entries()
    {
        $entry = AdminLedgerController::logEntry(
            'PLATFORM_COMMISSION',
            'CREDIT',
            250.00,
            '10% commission entry'
        );

        // Attempt to mutate or delete should be blocked by Nawshad's boot() hooks
        $updated = $entry->update(['amount' => 9999.00]);
        $deleted = $entry->delete();

        $this->assertFalse($updated);
        $this->assertFalse($deleted);
        $this->assertDatabaseHas('financial_ledgers', [
            'id' => $entry->id,
            'amount' => 250.00,
        ]);
    }

    /** @test */
    public function it_processes_manual_and_batch_settlements()
    {
        $shop = RepairShop::factory()->create(['name' => 'Gadget Den']);

        $settleResp = $this->post('/admin/financial-overview/settle', [
            'shop_id' => $shop->id,
            'gross_amount' => 2000.00,
            'note' => 'Bench repair #101',
        ]);

        $settleResp->assertRedirect('/admin/financial-overview');
        $this->assertDatabaseHas('financial_ledgers', [
            'shop_id' => $shop->id,
            'entry_type' => 'CUSTOMER_PAYMENT',
            'amount' => 2160.00,
        ]);
        $this->assertDatabaseHas('financial_ledgers', [
            'shop_id' => $shop->id,
            'entry_type' => 'PLATFORM_COMMISSION',
            'amount' => 160.00,
        ]);
        $this->assertDatabaseHas('financial_ledgers', [
            'shop_id' => $shop->id,
            'entry_type' => 'VENDOR_EARNING',
            'amount' => 2000.00,
        ]);

        // Filtering by Manual Audit Adjustment (`entry_type=ADJUSTMENT`) surfaces manual settlements
        $this->get('/admin/financial-overview?entry_type=ADJUSTMENT')
            ->assertStatus(200)
            ->assertSee('Gadget Den')
            ->assertSee('Bench repair #101');

        // Run batch auto-settlement to disburse VENDOR_EARNING (100% vendor subtotal) into VENDOR_PAYOUT
        $batchResp = $this->post('/admin/financial-overview/auto-settle', [
            'limit' => 10,
        ]);

        $batchResp->assertRedirect('/admin/financial-overview');
        $this->assertDatabaseHas('financial_ledgers', [
            'shop_id' => $shop->id,
            'entry_type' => 'VENDOR_PAYOUT',
            'direction' => 'DEBIT',
            'amount' => 2000.00,
        ]);
    }

    /** @test */
    public function it_supports_bulk_deleting_audits_records_only_or_undoing_ledger_effects()
    {
        $shop = RepairShop::factory()->create(['name' => 'Alpha Audit Lab']);
        $res1 = AdminLedgerController::settleOrderWithCommission(1000.00, $shop->id, null, null, 0.10, '[Manual Settlement] First batch');
        $res2 = AdminLedgerController::settleOrderWithCommission(2000.00, $shop->id, null, null, 0.10, '[Manual Settlement] Second batch');

        // 1. Delete Records Only on first commission entry: hidden from journal, but ৳300.00 total commission preserved
        $this->post('/admin/financial-overview/bulk-delete', [
            'entry_ids' => [$res1['commission_entry']->id],
            'delete_mode' => 'records_only',
        ])->assertRedirect('/admin/financial-overview');

        $this->assertNotNull($res1['commission_entry']->fresh()->hidden_at);
        $this->get('/admin/financial-overview')
            ->assertStatus(200)
            ->assertSee('300.00')
            ->assertDontSee('[Manual Settlement] First batch — Note')
            ->assertSee('Second batch');

        // 2. Delete & Undo Ledger Effects on second batch: entries removed AND commission total drops back to ৳100.00
        $this->post('/admin/financial-overview/bulk-delete', [
            'entry_ids' => [
                $res2['payment_entry']->id,
                $res2['commission_entry']->id,
                $res2['vendor_entry']->id,
            ],
            'delete_mode' => 'undo_effects',
        ])->assertRedirect('/admin/financial-overview');

        $this->assertDatabaseMissing('financial_ledgers', ['id' => $res2['commission_entry']->id]);
        $this->get('/admin/financial-overview')
            ->assertStatus(200)
            ->assertSee('100.00');

        // 3. Automated order/system entries (with payment_id) CANNOT be deleted even via direct POST
        $customer = \App\Models\User::factory()->create(['role' => 'customer']);
        $payment = \App\Models\PaymentTransaction::create([
            'user_id' => $customer->id,
            'amount' => 500.00,
            'currency' => 'BDT',
            'payment_method' => 'BKASH',
            'status' => 'PAID',
            'transaction_reference' => 'TRX-IMMUTABLE-01',
            'paid_at' => now(),
        ]);
        $autoRes = AdminLedgerController::settleOrderWithCommission(500.00, $shop->id, $payment->id);

        $this->post('/admin/financial-overview/bulk-delete', [
            'entry_ids' => [$autoRes['payment_entry']->id, $autoRes['commission_entry']->id],
            'delete_mode' => 'undo_effects',
        ])->assertRedirect('/admin/financial-overview')
          ->assertSessionHas('warning');

        $this->assertDatabaseHas('financial_ledgers', ['id' => $autoRes['payment_entry']->id, 'hidden_at' => null]);
        $this->assertDatabaseHas('financial_ledgers', ['id' => $autoRes['commission_entry']->id, 'hidden_at' => null]);
    }

    /** @test */
    public function it_issues_customer_refunds_against_settled_payments_and_logs_to_ledger()
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $customer = \App\Models\User::factory()->create(['role' => 'customer']);
        $shop = RepairShop::factory()->create(['name' => 'Refund Target Lab', 'is_active' => true, 'is_public' => true]);

        // Verify the seed migration executes cleanly against a populated database (matching Render/Neon)
        $seedMigration = require database_path('migrations/2026_11_13_000016_seed_refund_pending_orders.php');
        $seedMigration->up();
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-2026-RFD-PEND01']);
        $this->assertDatabaseHas('dispute_cases', ['status' => 'INVESTIGATING']);

        $payment = \App\Models\PaymentTransaction::create([
            'user_id' => $customer->id,
            'amount' => 3500.00,
            'currency' => 'BDT',
            'payment_method' => 'BKASH',
            'status' => 'PAID',
            'transaction_reference' => 'TRX-TEST-RFD-01',
            'paid_at' => now(),
        ]);

        $this->post('/admin/financial-overview/issue-refund', [
            'payment_id' => $payment->id,
            'refund_amount' => 3500.00,
            'reason' => 'Vendor failed post-settlement warranty check',
        ])->assertRedirect('/admin/financial-overview');

        $this->assertEquals('REFUNDED', $payment->fresh()->status);
        $this->assertDatabaseHas('financial_ledgers', [
            'payment_id' => $payment->id,
            'entry_type' => 'REFUND',
            'direction' => 'DEBIT',
            'amount' => 3500.00,
        ]);
        $this->get('/admin/financial-overview')
            ->assertStatus(200)
            ->assertSee('3,500.00');
    }

    /** @test */
    public function it_enforces_industry_standard_treasury_and_refund_clawback_accounting()
    {
        $customer = \App\Models\User::factory()->create(['role' => 'customer']);
        $shop = RepairShop::factory()->create(['name' => 'Escrow Integrity Lab']);

        $payment = \App\Models\PaymentTransaction::create([
            'user_id' => $customer->id,
            'amount' => 4860.00,
            'currency' => 'BDT',
            'payment_method' => 'BKASH',
            'status' => 'PAID',
            'transaction_reference' => 'TRX-ESCROW-STD-01',
            'paid_at' => now(),
        ]);

        // Settle ৳4,500 vendor subtotal (excl. fee): Customer Inflow = ৳4,860 (with 8% fee), Commission = ৳360, Vendor Escrow = ৳4,500
        AdminLedgerController::settleOrderWithCommission(4500.00, $shop->id, $payment->id);

        $this->assertSame(1, AdminLedgerController::countUnsettledShops());

        // Issuing full ৳4,860 refund must claw back 100% of vendor escrow (৳4,500) and reverse ৳360 platform commission
        $this->post('/admin/financial-overview/issue-refund', [
            'payment_id' => $payment->id,
            'refund_amount' => 4860.00,
            'reason' => 'Full refund clawback integrity check',
        ])->assertRedirect('/admin/financial-overview');

        // Shop must NO LONGER be eligible for vendor payout after full refund
        $this->assertSame(0, AdminLedgerController::countUnsettledShops());

        $this->assertDatabaseHas('financial_ledgers', [
            'shop_id' => $shop->id,
            'payment_id' => $payment->id,
            'entry_type' => 'PLATFORM_COMMISSION',
            'direction' => 'DEBIT',
            'amount' => 360.00,
        ]);

        // Batch auto-settle must NOT create any VENDOR_PAYOUT for this refunded order
        $this->post('/admin/financial-overview/auto-settle', ['limit' => 20])
            ->assertRedirect('/admin/financial-overview');

        $this->assertDatabaseMissing('financial_ledgers', [
            'shop_id' => $shop->id,
            'entry_type' => 'VENDOR_PAYOUT',
        ]);
    }

    /** @test */
    public function it_allows_admin_to_adjust_shop_commission_rate_between_5_and_10_percent_and_renders_bi_graphs()
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $vendorUser = \App\Models\User::factory()->create(['role' => 'vendor', 'is_active' => true]);
        $vendorProfile = \App\Models\VendorProfile::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Apex Precision Lab',
            'verification_status' => 'approved',
            'commission_rate' => 8.00,
        ]);
        $shop = RepairShop::factory()->create([
            'vendor_profile_id' => $vendorProfile->id,
            'name' => 'Apex Precision Lab',
            'is_active' => true,
            'is_public' => true,
        ]);

        AdminLedgerController::settleOrderWithCommission(5000.00, $shop->id);

        // Bounds outside 5%–10% must fail validation
        $this->actingAs($admin)
            ->post("/admin/shops/{$shop->id}/commission-rate", ['commission_rate' => 4.0])
            ->assertSessionHasErrors('commission_rate');

        $this->actingAs($admin)
            ->post("/admin/shops/{$shop->id}/commission-rate", ['commission_rate' => 12.0])
            ->assertSessionHasErrors('commission_rate');

        // Valid rate 6.5% saves onto vendor_profile & writes AuditLog
        $this->actingAs($admin)
            ->post("/admin/shops/{$shop->id}/commission-rate", ['commission_rate' => 6.5])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(6.50, (float) $vendorProfile->fresh()->commission_rate);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'vendor_commission_rate_updated',
            'target_resource' => "vendor_profiles:{$vendorProfile->id}",
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertStatus(200)
            ->assertSee('Platform Revenue Trajectory')
            ->assertSee('Best Performing Workshops')
            ->assertSee('6.5');

        $this->actingAs($admin)
            ->get('/admin/financial-overview')
            ->assertStatus(200)
            ->assertSee('Platform Income Rate Graph')
            ->assertSee('Best Performing Shops')
            ->assertSee('6.5');
    }
}
