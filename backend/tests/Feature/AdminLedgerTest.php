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
        $response->assertSee('4,500.00');
        $response->assertSee('450.00');
        $response->assertSee('4,050.00');
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
            'entry_type' => 'PLATFORM_COMMISSION',
            'amount' => 200.00,
        ]);
        $this->assertDatabaseHas('financial_ledgers', [
            'shop_id' => $shop->id,
            'entry_type' => 'VENDOR_EARNING',
            'amount' => 1800.00,
        ]);

        // Run batch auto-settlement to disburse VENDOR_EARNING into VENDOR_PAYOUT
        $batchResp = $this->post('/admin/financial-overview/auto-settle', [
            'limit' => 10,
        ]);

        $batchResp->assertRedirect('/admin/financial-overview');
        $this->assertDatabaseHas('financial_ledgers', [
            'shop_id' => $shop->id,
            'entry_type' => 'VENDOR_PAYOUT',
            'direction' => 'DEBIT',
            'amount' => 1800.00,
        ]);
    }
}
