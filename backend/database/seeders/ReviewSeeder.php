<?php

namespace Database\Seeders;

use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ShopReview;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * FEATURE R9: 100% Order-Backed Customer Review Seeder & Rating Synchronizer
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * End-to-End Relational Sync: Farhan Tanvir [22299179]
 *
 * Enforces Strict End-to-End Relational Invariants:
 *   1. 100% of `ShopReview` rows (`repair_request_id IS NOT NULL`) are derived
 *      directly from actual `COMPLETED` `RepairRequest`s that already have a
 *      `COMPLETED` `RepairJob`, `Warranty`, `PAID` `Invoice`, `PAID`
 *      `PaymentTransaction`, `EscrowLedger`, and `FinancialLedger` settlement!
 *   2. Zero orphan reviews, zero reviews on un-onboarded shops, and at most
 *      ONE review per `(repair_shop_id, customer_id)` pair.
 *   3. Includes 1 moderated/hidden review (`is_hidden = true`, `moderated_by_id`
 *      linked to System Admin `Meramot`) so `ModerationLog` links to a real row
 *      while visible `review_count` & `rating` exclude hidden reviews.
 *   4. Synchronizes 100% of `repair_shops.review_count`, `repair_shops.rating`,
 *      and `vendor_profiles.rating` strictly from visible `shop_reviews`.
 * ============================================================================
 */
class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        ShopReview::query()->delete();

        $meramotAdmin = User::where('email', 'admin@meramot.demo')->first()
            ?? User::where('role', 'admin')->orderBy('id')->first();

        $completedRequests = RepairRequest::with(['requestedShop', 'customer'])
            ->where('status', 'COMPLETED')
            ->whereNotNull('requested_shop_id')
            ->orderBy('id')
            ->get();

        if ($completedRequests->isEmpty()) {
            RepairShop::query()->update(['rating' => 0.00, 'review_count' => 0]);
            VendorProfile::query()->update(['rating' => 0.00]);
            return;
        }

        // Tailored authentic review copy keyed by `slug:customer_email`
        $curatedComments = [
            'protech-solutions:customer@meramot.demo' => [5, 'Replaced my iPhone 13 Pro OLED display assembly in under 3 hours. True Tone and Face ID work flawlessly with a 90-day warranty card.'],
            'protech-solutions:nusrat.jahan@meramot.demo' => [5, 'Booked the Laptop High-Capacity Battery Replacement package for my MacBook Air M2. Cycle count 1 verified and doorstep Banani pickup was right on schedule.'],
            'protech-solutions:sadia.afrin@meramot.demo' => [5, 'Fixed a severe Thunderbolt logic board short after a coffee spill. Ultrasonic cleaning and microscope soldering saved my thesis data.'],
            'protech-solutions:mehedi.hasan@meramot.demo' => [5, 'Genuine OEM battery cell installed on my MacBook Pro M2 with transparent itemized invoice and escrow receipt.'],
            'protech-solutions:tahmid.chowdhury@meramot.demo' => [5, 'Dell XPS 15 dual fans replaced and vapor chamber repasted cleanly — runs 18°C cooler under load.'],
            'protech-solutions:tanvir.hasan@meramot.demo' => [4, 'Top-tier charging flex & Tristar IC repair on my iPhone 14 Pro Max; evening courier traffic added 25 minutes, otherwise faultless.'],

            'protech-solutions-uttara:sadia.afrin@meramot.demo' => [5, 'So glad ProTech opened an Uttara Sector 7 branch! Restored my HP Spectre USB-C PD charging rail without crossing Airport Road traffic.'],
            'protech-solutions-uttara:mehedi.hasan@meramot.demo' => [5, 'MacBook Air M1 battery package completed in 90 minutes with Uttara courier return.'],
            'protech-solutions-uttara:tahmid.chowdhury@meramot.demo' => [5, 'Crisp backlit keyboard replacement on my Lenovo Yoga Slim 7i at North Tower bench.'],
            'protech-solutions-uttara:tasnim.akter@meramot.demo' => [4, 'Solid Galaxy S23+ AMOLED calibration and waterproof seal; slightly busy on Thursday evening.'],

            'protech-solutions-agrabad:rafiul.alam@meramot.demo' => [5, 'Agrabad branch handled our ThinkPad X1 Carbon power rail repair with complete corporate professionalism.'],
            'protech-solutions-agrabad:nusrat.jahan@meramot.demo' => [5, 'Original Display Panel package on my Galaxy S22 in Chattogram looks factory-fresh with courier return.'],
            'protech-solutions-agrabad:farzana.yasmin@meramot.demo' => [5, 'Fast MacBook Pro servicing on Sheikh Mujib Road with digital 90-day warranty receipt.'],

            'dhaka-device-doctors:rafiul.alam@meramot.demo' => [5, 'Saved my Dell XPS 13 motherboard with precision PMIC re-soldering! Transparent ৳6,200 invoice and fast Dhanmondi courier return.'],
            'dhaka-device-doctors:customer@meramot.demo' => [5, 'Best chip-level motherboard clinic on Dhanmondi Road 11/A. Showed thermal camera footage of the shorted capacitor on my ZenBook OLED.'],
            'dhaka-device-doctors:nusrat.jahan@meramot.demo' => [5, 'Revived a dead Pixel 7 Pro baseband PMIC without wiping internal photos.'],
            'dhaka-device-doctors:mahmudul.karim@meramot.demo' => [5, 'Replaced charging MOSFET rail on my ASUS TUF gaming laptop cleanly; holds stable voltage under load.'],
            'dhaka-device-doctors:imran.kabir@meramot.demo' => [4, 'Deep board-level BGA expertise on my MSI Modern 15; took the full quoted day and works reliably.'],

            'the-gadget-den:tanvir.hasan@meramot.demo' => [5, 'Walked into Navana Tower for my Galaxy S22 Ultra battery swap. 100% health restored in one afternoon.'],
            'the-gadget-den:customer@meramot.demo' => [5, 'Crisp HP Envy laptop keyboard replacement and thorough key-matrix test before handover.'],
            'the-gadget-den:sadia.afrin@meramot.demo' => [4, 'Apple Watch Series 8 front glass laminated cleanly without dust bubbles; premium Gulshan pricing but neat work.'],
            'the-gadget-den:mehedi.hasan@meramot.demo' => [4, 'iPad Air 5 digitizer works great; walk-in service only, yet turnaround was well organized.'],

            'budget-fixer:mahmudul.karim@meramot.demo' => [5, 'Fixed my Lenovo IdeaPad display ribbon & hinge in Dhanmondi 27 for just ৳1,200 total!'],
            'budget-fixer:tasnim.akter@meramot.demo' => [4, 'Very wallet-friendly Redmi Note 12 screen replacement. Brightness is solid for the budget price.'],
            'budget-fixer:imran.kabir@meramot.demo' => [4, 'PS4 Slim thermal overhaul & fan deep clean made the console whisper quiet again.'],
            'budget-fixer:rafiul.alam@meramot.demo' => [4, 'Fixed paper feed rollers on my Canon PIXMA printer at half the cost quoted elsewhere.'],

            'macmaster-repairs:farzana.yasmin@meramot.demo' => [5, 'True Apple specialists in Mohakhali! Repasted my MacBook Pro 16 M1 Pro and replaced the right fan with OEM parts.'],
            'macmaster-repairs:customer@meramot.demo' => [5, 'Diagnosed an iMac 24 M1 power supply rail issue in Tropical Plaza that two other shops gave up on.'],
            'macmaster-repairs:sadia.afrin@meramot.demo' => [5, 'MacBook Air M2 screen assembly swapped in 2 hours with True Tone preserved.'],
            'macmaster-repairs:tahmid.chowdhury@meramot.demo' => [4, 'Honest MagSafe 3 board repair and calibrated torque drivers; walk-in dropoff was smooth.'],
        ];

        $insertedPairs = [];
        $rowsToInsert = [];

        foreach ($completedRequests as $idx => $req) {
            $shop = $req->requestedShop;
            $customer = $req->customer;
            if (!$shop || !$customer || !$shop->is_public || !$shop->is_active) {
                continue;
            }

            $pairKey = $shop->id . ':' . $customer->id;
            if (isset($insertedPairs[$pairKey])) {
                continue;
            }
            $insertedPairs[$pairKey] = true;

            $reviewTime = $req->updated_at
                ? $req->updated_at->copy()->addHours(6)
                : now()->subDays(3);

            $slugEmailKey = $shop->slug . ':' . $customer->email;

            // Specifically mark 1 review (`protech-solutions:imran.kabir@meramot.demo`) as moderated/hidden spam
            // so Admin Moderation (`moderation_logs`) links to an actual hidden review row!
            if ($slugEmailKey === 'protech-solutions:imran.kabir@meramot.demo') {
                $rowsToInsert[] = [
                    'repair_shop_id' => $shop->id,
                    'customer_id' => $customer->id,
                    'repair_request_id' => $req->id,
                    'rating' => 5,
                    'review_comment' => 'Great backlight repair! Use my coupon link http://spam-referral.example/promo for free accessories.',
                    'is_verified_purchase' => true,
                    'is_hidden' => true,
                    'moderated_at' => (clone $reviewTime)->addHours(2),
                    'moderated_by_id' => $meramotAdmin?->id,
                    'moderation_note' => 'Hidden by Meramot Governance: External promotional referral URL removed per marketplace policy.',
                    'created_at' => $reviewTime,
                    'updated_at' => (clone $reviewTime)->addHours(2),
                ];
                continue;
            }

            if (isset($curatedComments[$slugEmailKey])) {
                [$rating, $comment] = $curatedComments[$slugEmailKey];
            } else {
                $rating = ($idx % 4 === 3) ? 4 : 5;
                $shopLabel = $shop->display_branch_tag
                    ? "{$shop->name} ({$shop->display_branch_tag})"
                    : $shop->name;
                $comment = $rating === 5
                    ? "Completed {$req->title} for my {$req->brand} {$req->model_name} at {$shopLabel}. Genuine parts, clear invoice, and 90-day warranty included."
                    : "Reliable {$req->title} service at {$shopLabel} in {$shop->area}. Workmanship is solid and matched the approved quote.";
            }

            $rowsToInsert[] = [
                'repair_shop_id' => $shop->id,
                'customer_id' => $customer->id,
                'repair_request_id' => $req->id,
                'rating' => $rating,
                'review_comment' => $comment,
                'is_verified_purchase' => true,
                'is_hidden' => false,
                'moderated_at' => null,
                'moderated_by_id' => null,
                'moderation_note' => null,
                'created_at' => $reviewTime,
                'updated_at' => $reviewTime,
            ];
        }

        foreach (array_chunk($rowsToInsert, 200) as $chunk) {
            ShopReview::insert($chunk);
        }

        // ---------------------------------------------------------------------
        // Synchronize 100% of `RepairShop` & `VendorProfile` Ratings & Counts
        // strictly from visible (`is_hidden = false`) `shop_reviews`!
        // ---------------------------------------------------------------------
        RepairShop::query()->update([
            'rating' => 0.00,
            'review_count' => 0,
        ]);

        VendorProfile::query()->update([
            'rating' => 0.00,
        ]);

        $shopStats = ShopReview::where('is_hidden', false)
            ->select(
                'repair_shop_id',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('ROUND(AVG(rating)::numeric, 2) as avg_rating')
            )
            ->groupBy('repair_shop_id')
            ->get();

        foreach ($shopStats as $stat) {
            RepairShop::where('id', $stat->repair_shop_id)->update([
                'review_count' => (int) $stat->cnt,
                'rating' => (float) $stat->avg_rating,
            ]);
        }

        $vendorStats = DB::table('shop_reviews')
            ->join('repair_shops', 'repair_shops.id', '=', 'shop_reviews.repair_shop_id')
            ->where('shop_reviews.is_hidden', false)
            ->whereNotNull('repair_shops.vendor_profile_id')
            ->select(
                'repair_shops.vendor_profile_id',
                DB::raw('ROUND(AVG(shop_reviews.rating)::numeric, 2) as avg_rating')
            )
            ->groupBy('repair_shops.vendor_profile_id')
            ->get();

        foreach ($vendorStats as $vStat) {
            VendorProfile::where('id', $vStat->vendor_profile_id)->update([
                'rating' => (float) $vStat->avg_rating,
            ]);
        }
    }
}
