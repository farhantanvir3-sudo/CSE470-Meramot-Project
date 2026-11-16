<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ServicePackage;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherSystemEndToEndTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function homepage_featured_voucher_stack_rotation_alerts_and_single_tile_fallback_work()
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@meramot.demo')->firstOrFail();

        // 1. Seeded homepage shows 2 tiles: Free courier on first repair + #1 Featured Voucher (Laptop battery week)
        $homeResp = $this->get('/');
        $homeResp->assertStatus(200)
            ->assertSee('data-offer-count="2"', false)
            ->assertSee('Free courier on your first repair')
            ->assertDontSee('Free pickup on your first repair')
            ->assertSee('FREECOURIER')
            ->assertSee('Laptop battery week')
            ->assertSee('BATTERY15')
            ->assertDontSee('Universal Voucher');

        // 2. When #1 (BATTERY15) is about to end (within 2h rotation window), #2 (BKASH10) automatically rotates onto Homepage Tile 2
        $battery = Voucher::where('code', 'BATTERY15')->firstOrFail();
        $this->actingAs($admin)->put("/admin/vouchers/{$battery->id}", [
            'code' => 'BATTERY15',
            'title' => $battery->title,
            'banner_subtitle' => $battery->banner_subtitle,
            'voucher_type' => 'LIMITED_TIME',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => 15,
            'min_order_amount' => 800,
            'max_discount_amount' => 500,
            'is_featured' => 1,
            'featured_order' => 1,
            'starts_at' => now()->subDays(2)->format('Y-m-d\TH:i'),
            'expires_at' => now()->addMinutes(45)->format('Y-m-d\TH:i'),
        ])->assertRedirect('/admin/vouchers');

        $rotatedHome = $this->get('/');
        $rotatedHome->assertStatus(200)
            ->assertSee('data-offer-count="2"', false)
            ->assertSee('10% off if you pay with bKash')
            ->assertSee('BKASH10');

        // 3. Leave only 1 Featured Voucher active with <48h left -> Admin Dashboard warns beforehand (Low Stack)
        Voucher::where('issuer_type', Voucher::ISSUER_ADMIN)
            ->where('is_system_default', false)
            ->where('code', '!=', 'BKASH10')
            ->update(['is_active' => false]);

        Voucher::where('code', 'BKASH10')->update([
            'expires_at' => now()->addHours(20),
        ]);

        $this->actingAs($admin)->get('/admin')
            ->assertStatus(200)
            ->assertSee('data-featured-voucher-low-alert', false)
            ->assertSee('Featured Voucher Stack Running Low');

        // 4. End all Featured Vouchers -> Admin Dashboard pops up Empty Modal AND Homepage collapses to 1 full-width tile
        Voucher::where('issuer_type', Voucher::ISSUER_ADMIN)
            ->where('is_system_default', false)
            ->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($admin)->get('/admin')
            ->assertStatus(200)
            ->assertSee('data-featured-voucher-empty-modal', false)
            ->assertSee('All Featured Vouchers Have Ended');

        $singleTileHome = $this->get('/');
        $singleTileHome->assertStatus(200)
            ->assertSee('data-offer-count="1"', false)
            ->assertSee('grid-cols-1', false)
            ->assertSee('Free courier on your first repair')
            ->assertDontSee('10% off if you pay with bKash');
    }

    /** @test */
    public function first_order_free_courier_voucher_works_once_for_first_order_and_blocks_subsequent_orders()
    {
        $this->seed(DatabaseSeeder::class);

        $freshCustomer = User::factory()->create([
            'role' => 'customer',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'phone' => '01712345678',
        ]);

        $pkg = ServicePackage::where('is_active', true)->where('fixed_price', '>=', 800)->firstOrFail();
        $shop = RepairShop::where('slug', 'protech-solutions')->firstOrFail();
        $shop->servicePackages()->syncWithoutDetaching([$pkg->id]);

        // 1st order on Meramot: FREECOURIER waives pickup & delivery fee
        $this->actingAs($freshCustomer)->post('/cart/add', [
            'package_id' => $pkg->id,
            'shop_id' => $shop->id,
            'quantity' => 1,
        ])->assertStatus(302);

        $applyResp = $this->actingAs($freshCustomer)
            ->postJson('/cart/voucher/apply', ['voucher_code' => 'FREECOURIER'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('voucher_code', 'FREECOURIER')
            ->assertJsonPath('discount_type', 'FREE_COURIER');

        $this->assertGreaterThan(0, (float) $applyResp->json('discount'));

        $this->actingAs($freshCustomer)->post('/cart/checkout/place', [
            'shop_id' => $shop->id,
            'fulfillment_mode' => 'COURIER_PICKUP',
            'schedule_type' => 'NOW',
            'delivery_type' => 'REGULAR',
            'payment_method' => 'CASH',
            'area' => 'Banani',
            'address' => 'House 10, Road 11, Banani, Dhaka',
            'phone' => '01712345678',
        ])->assertStatus(302);

        $firstOrder = RepairRequest::where('customer_id', $freshCustomer->id)->firstOrFail();
        $this->assertEquals('FREECOURIER', $firstOrder->voucher_code);
        $this->assertTrue(VoucherRedemption::where('user_id', $freshCustomer->id)->where('voucher_code', 'FREECOURIER')->exists());

        // 2nd order attempt on Meramot (even at a different workshop): FREECOURIER is strictly rejected
        $otherShop = RepairShop::where('slug', 'dhaka-device-doctors')->firstOrFail();
        $otherShop->servicePackages()->syncWithoutDetaching([$pkg->id]);

        $this->actingAs($freshCustomer)->post('/cart/add', [
            'package_id' => $pkg->id,
            'shop_id' => $otherShop->id,
            'quantity' => 1,
        ])->assertStatus(302);

        $this->actingAs($freshCustomer)
            ->postJson('/cart/voucher/apply', ['voucher_code' => 'FREECOURIER'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function admin_featured_vouchers_exclusions_bkash_rule_and_scheduled_days_enforce_cleanly()
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@meramot.demo')->firstOrFail();
        $customer = User::where('email', 'customer@meramot.demo')->firstOrFail();
        $protech = RepairShop::where('slug', 'protech-solutions')->firstOrFail();
        $excludedShop = RepairShop::where('slug', 'dhaka-device-doctors')->firstOrFail();
        $pkg = ServicePackage::where('is_active', true)->where('fixed_price', '>=', 1000)->firstOrFail();
        $protech->servicePackages()->syncWithoutDetaching([$pkg->id]);
        $excludedShop->servicePackages()->syncWithoutDetaching([$pkg->id]);

        // Admin creates a manual-code bKash Featured Voucher excluding Dhaka Device Doctors
        $this->actingAs($admin)->post('/admin/vouchers', [
            'code' => 'BKASH20VIP',
            'title' => '20% Off with bKash',
            'voucher_type' => 'LIMITED_TIME',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => 20,
            'min_order_amount' => 500,
            'max_discount_amount' => 600,
            'required_payment_method' => 'BKASH',
            'is_featured' => 1,
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'expires_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'excluded_shop_ids' => [$excludedShop->id],
        ])->assertRedirect('/admin/vouchers');

        // Advertised on ProTech profile, NOT on excluded Dhaka Device Doctors profile
        $this->get('/shops/protech-solutions')
            ->assertStatus(200)
            ->assertSee('20% off (bKash): BKASH20VIP');

        $this->get('/shops/dhaka-device-doctors')
            ->assertStatus(200)
            ->assertDontSee('BKASH20VIP');

        // Rejected at excluded shop during checkout
        $this->actingAs($customer)->post('/cart/add', [
            'package_id' => $pkg->id,
            'shop_id' => $excludedShop->id,
            'quantity' => 1,
        ]);
        $this->actingAs($customer)
            ->postJson('/cart/voucher/apply', ['voucher_code' => 'BKASH20VIP'])
            ->assertStatus(422);

        // Accepted at ProTech, but enforces BKASH payment method when placing order
        $this->actingAs($customer)->post('/cart/clear');
        $this->actingAs($customer)->post('/cart/add', [
            'package_id' => $pkg->id,
            'shop_id' => $protech->id,
            'quantity' => 1,
        ]);

        $this->actingAs($customer)
            ->postJson('/cart/voucher/apply', ['voucher_code' => 'BKASH20VIP'])
            ->assertStatus(200)
            ->assertJsonPath('required_payment_method', 'BKASH');

        // Attempting CASH with BKASH20VIP fails validation
        $this->from('/cart/checkout')->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $protech->id,
            'fulfillment_mode' => 'COURIER_PICKUP',
            'schedule_type' => 'NOW',
            'delivery_type' => 'REGULAR',
            'payment_method' => 'CASH',
            'area' => 'Banani',
            'address' => 'House 12, Road 11, Banani, Dhaka',
            'phone' => '01712345678',
            'voucher_code' => 'BKASH20VIP',
        ])->assertRedirect('/cart/checkout')->assertSessionHasErrors('voucher_code');

        // Paying with BKASH succeeds
        $this->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $protech->id,
            'fulfillment_mode' => 'COURIER_PICKUP',
            'schedule_type' => 'NOW',
            'delivery_type' => 'REGULAR',
            'payment_method' => 'BKASH',
            'area' => 'Banani',
            'address' => 'House 12, Road 11, Banani, Dhaka',
            'phone' => '01712345678',
            'voucher_code' => 'BKASH20VIP',
        ])->assertStatus(302);

        $latestOrder = RepairRequest::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertEquals('BKASH20VIP', $latestOrder->voucher_code);
        $this->assertGreaterThan(0, (float) $latestOrder->discount_amount);

        // Scheduled day-of-week rule: voucher restricted to tomorrow's weekday is rejected today
        $otherWeekday = (now()->dayOfWeek + 2) % 7;
        $this->actingAs($admin)->post('/admin/vouchers', [
            'code' => 'OTHERDAY50',
            'title' => 'Scheduled Weekday Offer',
            'voucher_type' => 'LIMITED_TIME',
            'discount_type' => 'FIXED',
            'discount_value' => 50,
            'min_order_amount' => 200,
            'valid_days_of_week' => [$otherWeekday],
        ])->assertRedirect('/admin/vouchers');

        $this->actingAs($customer)->post('/cart/add', [
            'package_id' => $pkg->id,
            'shop_id' => $protech->id,
            'quantity' => 1,
        ]);
        $this->actingAs($customer)
            ->postJson('/cart/voucher/apply', ['voucher_code' => 'OTHERDAY50'])
            ->assertStatus(422);
    }

    /** @test */
    public function vendor_all_branches_and_branch_specific_vouchers_and_single_voucher_limit_work()
    {
        $this->seed(DatabaseSeeder::class);

        $protechBanani = RepairShop::where('slug', 'protech-solutions')->with('vendorProfile.user')->firstOrFail();
        $protechUttara = RepairShop::where('slug', 'protech-solutions-uttara')->firstOrFail();
        $vendorUser = $protechBanani->vendorProfile->user;
        $customer = User::where('email', 'customer@meramot.demo')->firstOrFail();

        // Vendor creates an ALL_BRANCHES voucher and a Uttara-ONLY branch voucher
        $this->actingAs($vendorUser)->post('/vendor/vouchers', [
            'code' => 'ALLPRO99',
            'title' => 'All ProTech Branches Promo',
            'voucher_type' => 'LIMITED_TIME',
            'branch_scope' => 'ALL_BRANCHES',
            'discount_type' => 'FIXED',
            'discount_value' => 99,
            'min_order_amount' => 400,
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'expires_at' => now()->addDays(15)->format('Y-m-d\TH:i'),
        ])->assertRedirect('/vendor/dashboard?tab=vouchers');

        $this->actingAs($vendorUser)->post('/vendor/vouchers', [
            'code' => 'UTTARA88',
            'title' => 'Uttara Sector 7 Exclusive',
            'voucher_type' => 'LIMITED_TIME',
            'branch_scope' => 'SPECIFIC_BRANCHES',
            'shop_ids' => [$protechUttara->id],
            'discount_type' => 'FIXED',
            'discount_value' => 88,
            'min_order_amount' => 400,
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'expires_at' => now()->addDays(15)->format('Y-m-d\TH:i'),
        ])->assertRedirect('/vendor/dashboard?tab=vouchers');

        // Uttara profile advertises both ALLPRO99 and UTTARA88 in Alfresco format
        $this->get('/shops/protech-solutions-uttara')
            ->assertStatus(200)
            ->assertSee('৳99 off ৳400: ALLPRO99')
            ->assertSee('৳88 off ৳400: UTTARA88')
            ->assertDontSee('Coupon Voucher')
            ->assertDontSee('Active Deals');

        // Banani profile advertises ALLPRO99, NOT UTTARA88
        $this->get('/shops/protech-solutions')
            ->assertStatus(200)
            ->assertSee('৳99 off ৳400: ALLPRO99')
            ->assertDontSee('UTTARA88');

        // Shop directory tiles remove meaningless bare "Voucher" / "Deal" tags and show Alfresco format + checkout dialog
        $this->get('/shops?q=ProTech')
            ->assertStatus(200)
            ->assertSee('PROTECH150')
            ->assertDontSee('>Voucher<', false)
            ->assertDontSee('>Deal<', false);

        // Single-voucher rule at checkout: applying a second voucher replaces the first (never stacks)
        $pkg = ServicePackage::where('is_active', true)->where('fixed_price', '>=', 1000)->firstOrFail();
        $protechBanani->servicePackages()->syncWithoutDetaching([$pkg->id]);

        $this->actingAs($customer)->post('/cart/add', [
            'package_id' => $pkg->id,
            'shop_id' => $protechBanani->id,
            'quantity' => 1,
        ]);

        // UTTARA88 rejected at Banani branch
        $this->actingAs($customer)
            ->postJson('/cart/voucher/apply', ['voucher_code' => 'UTTARA88'])
            ->assertStatus(422);

        // Apply MERAMOT10, then apply ALLPRO99 -> session holds ONLY ALLPRO99
        $this->actingAs($customer)
            ->postJson('/cart/voucher/apply', ['voucher_code' => 'MERAMOT10'])
            ->assertStatus(200);
        $this->assertEquals('MERAMOT10', session('cart_voucher_code'));

        $this->actingAs($customer)
            ->postJson('/cart/voucher/apply', ['voucher_code' => 'ALLPRO99'])
            ->assertStatus(200)
            ->assertJsonPath('discount', 99);
        $this->assertEquals('ALLPRO99', session('cart_voucher_code'));

        // Checkout page renders "Apply a voucher" dialog markup
        $this->actingAs($customer)->get('/cart/checkout')
            ->assertStatus(200)
            ->assertSee('Apply a voucher')
            ->assertSee('ALLPRO99');
    }

    /** @test */
    public function admin_featured_voucher_creation_handles_validation_errors_without_progress_loss()
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'email' => 'admin@meramot.test']);

        // 1. AJAX validation error returns 422 JSON with specific error keys
        $errResp = $this->actingAs($admin)->postJson('/admin/vouchers', [
            'code' => '', // missing code
            'title' => '', // missing title
            'voucher_type' => 'LIMITED_TIME',
            'discount_type' => 'PERCENTAGE',
        ]);
        $errResp->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'title']);

        $errResp2 = $this->actingAs($admin)->postJson('/admin/vouchers', [
            'code' => 'VALCHECK1',
            'title' => 'Validation Check',
            'voucher_type' => 'LIMITED_TIME',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => 150, // invalid: percentage > 95%
        ]);
        $errResp2->assertStatus(422)
            ->assertJsonValidationErrors(['discount_value']);

        // 2. Successful AJAX store returns 200 JSON with redirect and ok
        $succResp = $this->actingAs($admin)->postJson('/admin/vouchers', [
            'code' => 'AJAXPROMO15',
            'title' => 'Ajax Featured Promo',
            'banner_subtitle' => 'Save 15% instantly without form reloads',
            'cta_href' => '/shops?voucher=true',
            'voucher_type' => 'LIMITED_TIME',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => 15,
            'min_order_amount' => 500,
            'max_discount_amount' => 200,
            'is_featured' => 1,
            'featured_order' => 3,
            'starts_at' => now()->format('Y-m-d\TH:i'),
            'expires_at' => now()->addDays(7)->format('Y-m-d\TH:i'),
        ]);
        $succResp->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'redirect' => route('admin.vouchers.index'),
            ]);

        // 3. Admin view renders old input preservation and keep-open directive
        $viewResp = $this->actingAs($admin)
            ->withSession([
                '_old_input' => [
                    'code' => 'PRESERVED15',
                    'title' => 'My Preserved Title',
                    'discount_value' => '25',
                    'min_order_amount' => '350',
                    'valid_days_of_week' => [5], // Friday
                ],
                'errors' => new \Illuminate\Support\ViewErrorBag(),
            ])
            ->get('/admin/vouchers');

        $viewResp->assertStatus(200)
            ->assertSee('value="PRESERVED15"', false)
            ->assertSee('value="My Preserved Title"', false)
            ->assertSee('value="25"', false)
            ->assertSee('value="350"', false)
            ->assertSee('submitCreateForm');
    }
}
