<?php

namespace Tests\Feature;

use App\Models\DisputeCase;
use App\Models\PaymentTransaction;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\SparePartListing;
use App\Models\SparePartStockMovement;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SparePartInventoryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function createSellerWithShop(string $brandName = 'Concurrent Parts Lab'): array
    {
        $vendorUser = User::factory()->create([
            'role' => 'vendor',
            'phone' => '017' . rand(10000000, 99999999),
            'city' => 'Dhaka',
            'area' => 'Banani',
        ]);

        $profile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'shop_name' => $brandName,
            'brand_name' => $brandName,
            'owner_name' => $vendorUser->name,
            'business_email' => $vendorUser->email,
            'phone' => $vendorUser->phone,
            'city' => 'Dhaka',
            'area' => 'Banani',
            'shop_address' => 'Road 11, Banani, Dhaka',
            'verification_status' => 'approved',
            'spare_parts' => true,
        ]);

        $shop = RepairShop::create([
            'vendor_profile_id' => $profile->id,
            'name' => $brandName,
            'slug' => 'concurrent-parts-lab-' . uniqid(),
            'city' => 'Dhaka',
            'area' => 'Banani',
            'address' => 'Road 11, Banani, Dhaka',
            'latitude' => 23.7937,
            'longitude' => 90.4066,
            'categories' => ['SPARE_PARTS', 'IN_SHOP_REPAIR', 'COURIER_PICKUP'],
            'rating' => 4.9,
            'review_count' => 15,
            'inspection_fee' => 300,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'is_active' => true,
            'is_public' => true,
        ]);

        return [$vendorUser, $profile, $shop];
    }

    /** @test */
    public function last_hour_concurrent_purchases_prevent_overselling_and_maintain_per_spec_stock(): void
    {
        [, $profile, $shop] = $this->createSellerWithShop('Last Hour Stock Shop');

        $listing = SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'iphone-14-pro-screen-' . uniqid(),
            'product_group_key' => 'iphone-14-pro-screen',
            'sku' => 'SKU-CONC-1',
            'title' => 'iPhone 14 Pro Screen Assembly',
            'part_category' => 'Display & Touch',
            'brand' => 'Apple',
            'has_specs' => true,
            'spec_label' => 'Grade',
            'spec_options' => [
                ['code' => 'oled', 'name' => 'OLED Soft', 'price' => 8500, 'stock' => 2],
                ['code' => 'lcd', 'name' => 'Incell LCD', 'price' => 4200, 'stock' => 3],
            ],
            'base_price' => 4200,
            'stock_quantity' => 3,
            'sold_count' => 0,
            'returned_count' => 0,
            'is_active' => true,
        ]);

        $buyerA = User::factory()->create(['role' => 'customer']);
        $buyerB = User::factory()->create(['role' => 'customer']);

        // Buyer B prepares a cart for 2x LCD BEFORE Buyer A checks out (classic last-hour cart race)
        $this->actingAs($buyerB)->postJson('/cart/add', [
            'spare_part_id' => $listing->id,
            'spec_code' => 'lcd',
            'quantity' => 2,
        ])->assertStatus(200);
        $buyerBCart = session('cart');
        $buyerBShopId = session('cart_shop_id');
        $this->flushSession();

        // Buyer A buys 2x OLED first -> succeeds cleanly, leaving stock_quantity = 1, oled stock = 0
        DB::enableQueryLog();
        $respA = $this->actingAs($buyerA)->post('/cart/add', [
            'spare_part_id' => $listing->id,
            'spec_code' => 'oled',
            'quantity' => 2,
        ]);
        $respA->assertRedirect();

        $checkoutA = $this->actingAs($buyerA)->post('/cart/checkout/place', [
            'shop_id' => $shop->id,
            'schedule_type' => 'NOW',
            'delivery_type' => 'REGULAR',
            'payment_method' => 'CASH',
            'phone' => '01711112222',
            'area' => 'Banani',
            'address' => 'House 4, Road 11, Banani, Dhaka',
        ]);
        $checkoutA->assertRedirect();

        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();
        if (DB::getDriverName() === 'pgsql') {
            $this->assertStringContainsString('for update', strtolower($queries));
            $this->assertStringContainsString('order by "id" asc', strtolower($queries));
        }

        $listing->refresh();
        $this->assertSame(1, $listing->stock_quantity);
        $this->assertSame(2, $listing->sold_count);
        $this->assertSame(0, $listing->returned_count);
        $this->assertSame(0, $listing->availableStock('oled'));
        $this->assertSame(1, $listing->availableStock('lcd'));

        $this->flushSession();

        // Buyer B cannot add sold-out OLED spec
        $this->actingAs($buyerB)->postJson('/cart/add', [
            'spare_part_id' => $listing->id,
            'spec_code' => 'oled',
            'quantity' => 1,
        ])->assertStatus(422);

        // Buyer B attempts to check out previously captured 2x LCD cart -> must fail cleanly & roll back
        $this->flushSession();
        $ordersBeforeB = RepairRequest::where('customer_id', $buyerB->id)->count();
        $checkoutB = $this->actingAs($buyerB)
            ->withSession([
                'cart' => $buyerBCart,
                'cart_shop_id' => $buyerBShopId,
                'cart_shop_locked' => true,
            ])
            ->post('/cart/checkout/place', [
                'shop_id' => $shop->id,
                'schedule_type' => 'NOW',
                'delivery_type' => 'REGULAR',
                'payment_method' => 'CASH',
                'phone' => '01799998888',
                'area' => 'Banani',
                'address' => 'House 9, Road 11, Banani, Dhaka',
            ]);

        $checkoutB->assertSessionHasErrors('stock');
        $this->assertSame($ordersBeforeB, RepairRequest::where('customer_id', $buyerB->id)->count());

        $listing->refresh();
        $this->assertSame(1, $listing->stock_quantity);
        $this->assertSame(2, $listing->sold_count);
        $this->assertSame(0, $listing->returned_count);
    }

    /** @test */
    public function multi_item_checkout_locks_listings_in_ascending_id_order_to_prevent_deadlocks(): void
    {
        [, $profile, $shop] = $this->createSellerWithShop('Deadlock Free Shop');

        $partLow = SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'battery-a-' . uniqid(),
            'product_group_key' => 'battery-a',
            'title' => 'Laptop Battery Alpha',
            'part_category' => 'Laptop Battery',
            'brand' => 'Dell',
            'base_price' => 2500,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $partHigh = SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'fan-b-' . uniqid(),
            'product_group_key' => 'fan-b',
            'title' => 'Cooling Fan Beta',
            'part_category' => 'Laptop Cooling Fan',
            'brand' => 'Dell',
            'base_price' => 1200,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['role' => 'customer']);

        // Add higher ID before lower ID
        $this->actingAs($customer)->postJson('/cart/add', [
            'spare_part_id' => $partHigh->id,
            'quantity' => 2,
        ])->assertStatus(200);
        $this->actingAs($customer)->postJson('/cart/add', [
            'spare_part_id' => $partLow->id,
            'quantity' => 1,
        ])->assertStatus(200);

        DB::enableQueryLog();
        $this->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $shop->id,
            'schedule_type' => 'NOW',
            'delivery_type' => 'REGULAR',
            'payment_method' => 'CASH',
            'phone' => '01722334455',
            'area' => 'Banani',
            'address' => 'Road 11, Banani',
        ])->assertRedirect();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $this->assertStringContainsString('order by "id" asc', strtolower($queries));
        $this->assertSame(4, $partLow->fresh()->stock_quantity);
        $this->assertSame(1, $partLow->fresh()->sold_count);
        $this->assertSame(3, $partHigh->fresh()->stock_quantity);
        $this->assertSame(2, $partHigh->fresh()->sold_count);
    }

    /** @test */
    public function cancellation_rejection_return_and_dispute_refund_track_sold_and_returned_counts_idempotently(): void
    {
        [$vendorUser, $profile, $shop] = $this->createSellerWithShop('Return Lifecycle Shop');

        $listing = SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'ssd-nvme-' . uniqid(),
            'product_group_key' => 'ssd-nvme',
            'title' => 'NVMe SSD 512GB',
            'part_category' => 'SSD, RAM & Storage',
            'brand' => 'Samsung',
            'has_specs' => true,
            'spec_label' => 'Capacity',
            'spec_options' => [
                ['code' => '512gb', 'name' => '512GB NVMe', 'price' => 4500, 'stock' => 10],
            ],
            'base_price' => 4500,
            'stock_quantity' => 10,
            'sold_count' => 0,
            'returned_count' => 0,
            'is_active' => true,
        ]);

        // 1. Order #1: Customer places order (3 pcs) then cancels before dispatch -> restores stock & sold, returned stays 0
        $customer1 = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer1)->post('/cart/add', [
            'spare_part_id' => $listing->id,
            'spec_code' => '512gb',
            'quantity' => 3,
        ])->assertRedirect();
        $this->actingAs($customer1)->post('/cart/checkout/place', [
            'shop_id' => $shop->id,
            'schedule_type' => 'NOW',
            'delivery_type' => 'REGULAR',
            'payment_method' => 'CASH',
            'phone' => '01733334444',
            'area' => 'Banani',
            'address' => 'House 20, Banani',
        ])->assertRedirect();
        $order1 = RepairRequest::where('customer_id', $customer1->id)->latest('id')->firstOrFail();

        $this->assertSame(7, $listing->fresh()->stock_quantity);
        $this->assertSame(3, $listing->fresh()->sold_count);

        $this->actingAs($customer1)->post(route('requests.cancel', $order1->id), [
            'reason' => 'Ordered wrong capacity',
        ])->assertRedirect();

        $listing->refresh();
        $this->assertSame(10, $listing->stock_quantity);
        $this->assertSame(0, $listing->sold_count);
        $this->assertSame(0, $listing->returned_count);
        $this->assertSame(10, $listing->availableStock('512gb'));

        // Re-cancelling / hitting return endpoint on cancelled order does not double-restore
        $this->actingAs($customer1)->post(route('requests.return-parts', $order1->id))->assertSessionHas('error');
        $this->assertSame(10, $listing->fresh()->stock_quantity);

        // 2. Order #2: Customer buys 4 pcs, vendor accepts, order completes, customer returns via return-parts
        $this->flushSession();
        $customer2 = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer2)->post('/cart/add', [
            'spare_part_id' => $listing->id,
            'spec_code' => '512gb',
            'quantity' => 4,
        ])->assertRedirect();
        $this->actingAs($customer2)->post('/cart/checkout/place', [
            'shop_id' => $shop->id,
            'schedule_type' => 'NOW',
            'delivery_type' => 'REGULAR',
            'payment_method' => 'CASH',
            'phone' => '01744445555',
            'area' => 'Banani',
            'address' => 'House 21, Banani',
        ])->assertRedirect();
        $order2 = RepairRequest::where('customer_id', $customer2->id)->latest('id')->firstOrFail();
        $order2->update(['status' => RepairRequest::STATUS_COMPLETED]);

        $this->assertSame(6, $listing->fresh()->stock_quantity);
        $this->assertSame(4, $listing->fresh()->sold_count);

        $this->actingAs($customer2)->post(route('requests.return-parts', $order2->id), [
            'reason' => 'Incompatible connector',
        ])->assertRedirect();

        $listing->refresh();
        $this->assertSame(10, $listing->stock_quantity);
        $this->assertSame(0, $listing->sold_count);
        $this->assertSame(4, $listing->returned_count);
        $this->assertSame(10, $listing->availableStock('512gb'));
        $this->assertSame(RepairRequest::STATUS_RETURNED_TO_CUSTOMER, $order2->fresh()->status);

        // 3. Subsequent full dispute refund on Order #2 must NOT double-restore or double-count returns!
        $admin = User::factory()->create(['role' => 'admin']);
        $payment2 = PaymentTransaction::where('repair_request_id', $order2->id)->firstOrFail();
        $payment2->update(['status' => 'PAID']);
        $dispute = DisputeCase::create([
            'repair_request_id' => $order2->id,
            'repair_job_id' => $order2->repairJob->id,
            'payment_id' => $payment2->id,
            'opened_by_id' => $customer2->id,
            'against_id' => $vendorUser->id,
            'reason' => 'Refund requested after return',
            'description' => 'Testing idempotency between part return and dispute full refund.',
            'status' => 'OPEN',
        ]);

        $this->actingAs($admin)->post(route('admin.disputes.resolve', $dispute->id), [
            'decision' => 'full_refund',
            'resolution' => 'Full refund approved after returned parts inspection.',
        ])->assertRedirect();

        $listing->refresh();
        $this->assertSame(10, $listing->stock_quantity);
        $this->assertSame(0, $listing->sold_count);
        $this->assertSame(4, $listing->returned_count);

        // Ledger movement check
        $this->assertSame(
            1,
            SparePartStockMovement::where('repair_request_id', $order2->id)
                ->where('movement_type', SparePartStockMovement::TYPE_RETURN_RESTORE)
                ->count()
        );
    }

    /** @test */
    public function postgresql_for_update_lock_blocks_contending_connection_and_commits_cleanly(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL FOR UPDATE lock test requires pgsql driver.');
        }

        $cfg = DB::connection('pgsql')->getConfig();
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $cfg['host'] ?? '127.0.0.1',
            $cfg['port'] ?? 5432,
            $cfg['database']
        );
        $pdo1 = new \PDO($dsn, $cfg['username'], $cfg['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo2 = new \PDO($dsn, $cfg['username'], $cfg['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $email = 'pg-lock-vendor-' . uniqid() . '@meramot.test';
        $slug = 'pg-lock-shop-' . uniqid();
        $partSlug = 'pg-lock-part-' . uniqid();

        $userId = null;
        try {
            $pdo1->beginTransaction();
            $stmtU = $pdo1->prepare("INSERT INTO users (name, email, password, role, created_at, updated_at) VALUES ('Lock Vendor', ?, 'x', 'vendor', NOW(), NOW()) RETURNING id");
            $stmtU->execute([$email]);
            $userId = (int) $stmtU->fetchColumn();

            $stmtVp = $pdo1->prepare("INSERT INTO vendor_profiles (user_id, shop_name, owner_name, business_email, verification_status, spare_parts, created_at, updated_at) VALUES (?, 'Lock Lab', 'Lock Vendor', ?, 'approved', true, NOW(), NOW()) RETURNING id");
            $stmtVp->execute([$userId, $email]);
            $vpId = (int) $stmtVp->fetchColumn();

            $stmtS = $pdo1->prepare("INSERT INTO repair_shops (vendor_profile_id, name, slug, city, area, categories, setup_complete, accepts_direct_orders, is_active, is_public, created_at, updated_at) VALUES (?, 'Lock Lab', ?, 'Dhaka', 'Banani', '[\"SPARE_PARTS\"]', true, true, true, true, NOW(), NOW()) RETURNING id");
            $stmtS->execute([$vpId, $slug]);
            $shopId = (int) $stmtS->fetchColumn();

            $stmtP = $pdo1->prepare("INSERT INTO spare_part_listings (shop_id, vendor_profile_id, slug, product_group_key, title, part_category, brand, base_price, stock_quantity, sold_count, returned_count, is_active, created_at, updated_at) VALUES (?, ?, ?, 'pg-lock-group', 'Lock Contention Part', 'Battery', 'Dell', 1800, 3, 0, 0, true, NOW(), NOW()) RETURNING id");
            $stmtP->execute([$shopId, $vpId, $partSlug]);
            $partId = (int) $stmtP->fetchColumn();
            $pdo1->commit();

            // Connection 1 acquires row-level exclusive lock and deducts 2 units
            $pdo1->beginTransaction();
            $stmtLock1 = $pdo1->prepare('SELECT stock_quantity FROM spare_part_listings WHERE id = ? FOR UPDATE');
            $stmtLock1->execute([$partId]);
            $this->assertSame(3, (int) $stmtLock1->fetchColumn());

            // Connection 2 with 120ms lock_timeout MUST block/fail while Connection 1 holds FOR UPDATE
            $pdo2->beginTransaction();
            $pdo2->exec("SET LOCAL lock_timeout = '120ms'");
            $blocked = false;
            try {
                $stmtLock2 = $pdo2->prepare('SELECT stock_quantity FROM spare_part_listings WHERE id = ? FOR UPDATE');
                $stmtLock2->execute([$partId]);
            } catch (\PDOException $e) {
                $blocked = ($e->getCode() === '55P03' || str_contains(strtolower($e->getMessage()), 'lock timeout'));
            }
            $pdo2->rollBack();
            $this->assertTrue($blocked, 'Expected second concurrent transaction FOR UPDATE to block on row lock');

            // Connection 1 finishes its deduction and commits
            $pdo1->prepare('UPDATE spare_part_listings SET stock_quantity = stock_quantity - 2, sold_count = sold_count + 2 WHERE id = ?')->execute([$partId]);
            $pdo1->commit();

            // Connection 2 now immediately acquires FOR UPDATE and sees stock_quantity = 1 (never stale 3!)
            $pdo2->beginTransaction();
            $stmtLock2b = $pdo2->prepare('SELECT stock_quantity, sold_count FROM spare_part_listings WHERE id = ? FOR UPDATE');
            $stmtLock2b->execute([$partId]);
            $row = $stmtLock2b->fetch(\PDO::FETCH_ASSOC);
            $pdo2->rollBack();

            $this->assertSame(1, (int) $row['stock_quantity']);
            $this->assertSame(2, (int) $row['sold_count']);
        } finally {
            if ($pdo1->inTransaction()) {
                $pdo1->rollBack();
            }
            if ($pdo2->inTransaction()) {
                $pdo2->rollBack();
            }
            if ($userId) {
                $pdo1->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            }
            $pdo1 = null;
            $pdo2 = null;
        }
    }
}
