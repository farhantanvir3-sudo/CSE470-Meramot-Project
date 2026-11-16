<?php

namespace Database\Seeders;

use App\Http\Controllers\VendorBranchController;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ShopReview;
use App\Models\SparePartListing;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * FEATURE R9: 100% Order-Backed Repair & Spare Parts Customer Review Seeder
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * End-to-End Relational & Spare-Part Sync: Farhan Tanvir [22299179]
 *
 * Enforces Strict End-to-End Relational Invariants:
 *   1. 100% of `ShopReview` rows (`repair_request_id IS NOT NULL`) are derived
 *      directly from actual `COMPLETED` `RepairRequest`s with a `COMPLETED`
 *      `RepairJob`, `Warranty`, `PAID` `Invoice`, `PAID` `PaymentTransaction`,
 *      `EscrowLedger`, and `FinancialLedger` settlement!
 *   2. Includes verified Spare Parts reviews (`spare_part_listing_id IS NOT NULL`)
 *      linking directly to actual `SparePartListing` product pages (`/spare-parts/{slug}`).
 *   3. Zero orphan reviews, zero reviews on un-onboarded shops, and at most
 *      ONE review per `(repair_shop_id, customer_id)` pair.
 *   4. Synchronizes 100% of `repair_shops.review_count`, `repair_shops.rating`,
 *      and `vendor_profiles.rating` strictly from visible `shop_reviews`.
 * ============================================================================
 */
class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        ShopReview::query()->delete();

        $hasSparePartCol = Schema::hasColumn('shop_reviews', 'spare_part_listing_id');

        $meramotAdmin = User::where('email', 'admin@meramot.demo')->first()
            ?? User::where('role', 'admin')->orderBy('id')->first();

        $completedRequests = RepairRequest::with(['requestedShop.vendorProfile', 'customer', 'repairJob'])
            ->where('status', 'COMPLETED')
            ->whereNotNull('requested_shop_id')
            ->orderBy('id')
            ->get();

        if ($completedRequests->isEmpty() && (!Schema::hasTable('spare_part_listings') || SparePartListing::count() === 0)) {
            RepairShop::query()->update(['rating' => 0.00, 'review_count' => 0]);
            VendorProfile::query()->update(['rating' => 0.00]);
            return;
        }

        // Tailored authentic review copy keyed by `slug:customer_email`
        $curatedComments = [
            'protech-solutions:customer@meramot.demo' => [5, 'Replaced my iPhone 13 Pro OLED display assembly in under 3 hours. True Tone and Face ID work flawlessly with a 90-day warranty card.'],
            'protech-solutions:nusrat.jahan@meramot.demo' => [5, 'Booked the Thermal Repasting & Internal Dust Deep Clean package for my MacBook Air M2. Temperatures dropped 16°C and doorstep Banani pickup was right on schedule.'],
            'protech-solutions:sadia.afrin@meramot.demo' => [5, 'Fixed a severe Thunderbolt logic board short after a coffee spill. Ultrasonic cleaning and microscope soldering saved my thesis data.'],
            'protech-solutions:mehedi.hasan@meramot.demo' => [5, 'Genuine OEM battery cell installed on my MacBook Pro M2 with transparent itemized invoice and escrow receipt.'],
            'protech-solutions:tahmid.chowdhury@meramot.demo' => [5, 'Dell XPS 15 dual fans replaced and vapor chamber repasted cleanly — runs 18°C cooler under load.'],
            'protech-solutions:tanvir.hasan@meramot.demo' => [4, 'Top-tier charging flex & Tristar IC repair on my iPhone 14 Pro Max; evening courier traffic added 25 minutes, otherwise faultless.'],

            'protech-solutions-uttara:sadia.afrin@meramot.demo' => [5, 'So glad ProTech opened an Uttara Sector 7 branch! Restored my HP Spectre USB-C PD charging rail without crossing Airport Road traffic.'],
            'protech-solutions-uttara:mehedi.hasan@meramot.demo' => [5, 'MacBook Air M1 Clean OS & Drivers Setup package completed in 90 minutes with Uttara courier return.'],
            'protech-solutions-uttara:tahmid.chowdhury@meramot.demo' => [5, 'Crisp backlit keyboard replacement on my Lenovo Yoga Slim 7i at North Tower bench.'],
            'protech-solutions-uttara:tasnim.akter@meramot.demo' => [4, 'Solid Galaxy S23+ AMOLED calibration and waterproof seal; slightly busy on Thursday evening.'],

            'protech-solutions-agrabad:rafiul.alam@meramot.demo' => [5, 'Agrabad branch handled our ThinkPad X1 Carbon power rail repair with complete corporate professionalism.'],
            'protech-solutions-agrabad:nusrat.jahan@meramot.demo' => [5, 'Android Rooting & Firmware Flash package on my Galaxy S22 in Chattogram with clean EFS backup and courier return.'],
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
            if ($shop->vendorProfile && $shop->vendorProfile->verification_status !== 'approved') {
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

            // Detect if this completed order already purchased a SparePartListing
            $detectedPart = null;
            if ($hasSparePartCol && is_array($req->repairJob?->final_quote_items)) {
                foreach ($req->repairJob->final_quote_items as $qIt) {
                    if (is_array($qIt) && !empty($qIt['spare_part_id'])) {
                        $detectedPart = SparePartListing::find((int) $qIt['spare_part_id']);
                        if ($detectedPart) {
                            break;
                        }
                    }
                }
            }

            // Specifically mark 1 review (`protech-solutions:imran.kabir@meramot.demo`) as moderated/hidden spam
            // so Admin Moderation (`moderation_logs`) links to an actual hidden review row!
            if ($slugEmailKey === 'protech-solutions:imran.kabir@meramot.demo') {
                $row = [
                    'repair_shop_id' => $shop->id,
                    'customer_id' => $customer->id,
                    'repair_request_id' => $req->id,
                    'rating' => 5,
                    'service_provided' => $req->title ?: ($req->issue_category ?: 'Hardware Repair'),
                    'review_comment' => 'Great backlight repair! Use my coupon link http://spam-referral.example/promo for free accessories.',
                    'is_verified_purchase' => true,
                    'is_hidden' => true,
                    'moderated_at' => (clone $reviewTime)->addHours(2),
                    'moderated_by_id' => $meramotAdmin?->id,
                    'moderation_note' => 'Hidden by Meramot Governance: External promotional referral URL removed per marketplace policy.',
                    'created_at' => $reviewTime,
                    'updated_at' => (clone $reviewTime)->addHours(2),
                ];
                if ($hasSparePartCol) {
                    $row['spare_part_listing_id'] = null;
                }
                $rowsToInsert[] = $row;
                continue;
            }

            if ($detectedPart) {
                $rating = 5;
                $comment = "Ordered {$detectedPart->title} from {$shop->name}. Genuine factory-sealed component, matched exact model specs, and delivered safely.";
            } elseif (isset($curatedComments[$slugEmailKey])) {
                [$rating, $comment] = $curatedComments[$slugEmailKey];
            } else {
                $rating = ($idx % 5 === 4) ? 3 : (($idx % 4 === 3) ? 4 : 5);
                $shopLabel = $shop->display_branch_tag
                    ? "{$shop->name} ({$shop->display_branch_tag})"
                    : $shop->name;
                $comment = $rating === 5
                    ? "Completed {$req->title} for my {$req->brand} {$req->model_name} at {$shopLabel}. Genuine parts, clear invoice, and 90-day warranty included."
                    : ($rating === 3
                        ? "Fixed {$req->title} at {$shopLabel}, though part sourcing wait took an extra day."
                        : "Reliable {$req->title} service at {$shopLabel} in {$shop->area}. Workmanship is solid and matched the approved quote.");
            }

            $row = [
                'repair_shop_id' => $shop->id,
                'customer_id' => $customer->id,
                'repair_request_id' => $req->id,
                'rating' => $rating,
                'service_provided' => $detectedPart
                    ? Str::limit('Spare Part — ' . $detectedPart->title, 175, '')
                    : ($req->title ?: ($req->issue_category ?: 'Hardware Repair')),
                'review_comment' => $comment,
                'is_verified_purchase' => true,
                'is_hidden' => false,
                'moderated_at' => null,
                'moderated_by_id' => null,
                'moderation_note' => null,
                'created_at' => $reviewTime,
                'updated_at' => $reviewTime,
            ];
            if ($hasSparePartCol) {
                $row['spare_part_listing_id'] = $detectedPart?->id;
            }
            $rowsToInsert[] = $row;
        }

        // ---------------------------------------------------------------------
        // PART 2: Seed Order-Backed Verified Spare Parts Reviews (with direct
        // `spare_part_listing_id` links to actual product listings!)
        // ---------------------------------------------------------------------
        if ($hasSparePartCol && Schema::hasTable('spare_part_listings')) {
            $eligibleCustomers = User::where('role', 'customer')
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'banned');
                })
                ->orderBy('id')
                ->get();

            $priorityShopSlugs = [
                'protech-solutions',
                'protech-solutions-uttara',
                'protech-solutions-agrabad',
                'dhaka-device-doctors',
                'dragon-tech',
                'bytesize-repairs',
                'mirpur-laptop-lab',
                'the-gadget-den',
                'macmaster-repairs',
            ];

            $priorityShops = RepairShop::with('vendorProfile')
                ->whereIn('slug', $priorityShopSlugs)
                ->where('is_active', true)
                ->where('is_public', true)
                ->get();

            $wholesalerShops = RepairShop::with('vendorProfile')
                ->whereNotIn('slug', $priorityShopSlugs)
                ->where('is_active', true)
                ->where('is_public', true)
                ->whereHas('sparePartListings', fn ($q) => $q->where('is_active', true))
                ->orderByDesc('is_featured')
                ->orderBy('id')
                ->limit(6)
                ->get();

            $targetPartShops = $priorityShops->merge($wholesalerShops);

            $partCommentTemplates = [
                [5, 'Genuine %s arrived well-packed in anti-static protection. Exact connector fit and tested 100%% healthy right out of the box.'],
                [5, 'Purchased %s from %s — authentic part matching the listed SKU & specs, quick dispatch, and verified warranty coverage included.'],
                [5, 'Installed %s without any compatibility issues. Voltage and build quality match original factory standards.'],
                [4, 'Solid replacement %s from %s. Clean packaging and accurate product listing; courier arrived within the estimated window.'],
            ];

            $tplCursor = 0;
            foreach ($targetPartShops as $pShop) {
                if ($pShop->vendorProfile && $pShop->vendorProfile->verification_status !== 'approved') {
                    continue;
                }

                $shopParts = SparePartListing::where('shop_id', $pShop->id)
                    ->where('is_active', true)
                    ->where('stock_quantity', '>', 0)
                    ->orderByRaw('LOWER(TRIM(title)) ASC')
                    ->limit(3)
                    ->get();

                if ($shopParts->isEmpty()) {
                    continue;
                }

                $addedForShop = collect($rowsToInsert)
                    ->where('repair_shop_id', $pShop->id)
                    ->filter(fn ($r) => !empty($r['spare_part_listing_id']))
                    ->count();
                if ($addedForShop >= 2) {
                    continue;
                }

                foreach ($shopParts as $partItem) {
                    if ($addedForShop >= 2) {
                        break;
                    }

                    $chosenCustomer = $eligibleCustomers->first(function (User $u) use ($pShop, $insertedPairs) {
                        return !isset($insertedPairs[$pShop->id . ':' . $u->id]);
                    });

                    if (!$chosenCustomer) {
                        break;
                    }

                    $pairKey = $pShop->id . ':' . $chosenCustomer->id;
                    $insertedPairs[$pairKey] = true;

                    $unitPrice = max(150.0, round((float) $partItem->effectiveBasePrice(), 2));
                    $wDays = max(0, (int) ($partItem->warranty_days ?? 0));
                    $completedAt = now()->subDays(2 + ($tplCursor % 9))->subHours(3);
                    $usesCourier = (bool) $pShop->supports_pickup;

                    // Create / sync full order-backed COMPLETED RepairRequest + RepairJob + Ledger Settlement
                    $spReq = RepairRequest::updateOrCreate(
                        [
                            'customer_id' => $chosenCustomer->id,
                            'requested_shop_id' => $pShop->id,
                            'issue_category' => 'Spare Parts Order',
                            'model_name' => 'Verified Spare Part #' . $partItem->id,
                        ],
                        [
                            'source' => 'DIRECT_SERVICE',
                            'title' => $partItem->title,
                            'description' => "Verified Spare Part Order ({$pShop->name}): {$partItem->title}",
                            'problem' => "Spare Part Purchase: {$partItem->title}",
                            'device_type' => $partItem->part_category ?: 'Spare Part',
                            'brand' => $partItem->brand ?: $pShop->name,
                            'mode' => 'DIRECT_REPAIR',
                            'status' => RepairRequest::STATUS_COMPLETED,
                            'preferred_pickup' => $usesCourier,
                            'preferred_service_type' => $usesCourier ? 'pickup_dropoff' : 'walk_in',
                            'delivery_type' => $usesCourier ? 'REGULAR' : null,
                            'contact_phone' => $chosenCustomer->phone ?: '+8801711000099',
                            'pickup_address' => $usesCourier ? ($chosenCustomer->address ?: 'Road 11, Banani, Dhaka') : null,
                            'dropoff_address' => $pShop->public_address,
                            'checkup_fee' => round($unitPrice * ($pShop->effectiveCommissionRatePct() / 100.0), 2),
                            'quoted_final_amount' => $unitPrice,
                            'approved_at' => $completedAt->copy()->subHours(4),
                        ]
                    );

                    $spJob = RepairJob::updateOrCreate(
                        ['repair_request_id' => $spReq->id],
                        [
                            'shop_id' => $pShop->id,
                            'status' => 'COMPLETED',
                            'parts_cost' => $unitPrice,
                            'labor_cost' => 0,
                            'has_warranty' => $wDays > 0,
                            'warranty_days' => $wDays,
                            'diagnosis_notes' => "Dispatched verified spare part SKU {$partItem->sku}.",
                            'final_quoted_amount' => $unitPrice,
                            'final_quote_items' => [[
                                'id' => 'sp_' . $partItem->id,
                                'spare_part_id' => $partItem->id,
                                'slug' => $partItem->slug,
                                'title' => $partItem->title,
                                'price' => $unitPrice,
                                'part_unit_price' => $unitPrice,
                                'quantity' => 1,
                                'source' => 'SPARE_PART',
                                'has_warranty' => $wDays > 0,
                                'warranty_days' => $wDays,
                            ]],
                            'customer_approved' => true,
                            'started_at' => $completedAt->copy()->subHours(3),
                            'completed_at' => $completedAt,
                        ]
                    );

                    VendorBranchController::settleCompletedJobFinancials(
                        $spJob->fresh(['repairRequest', 'shop.vendorProfile'])
                    );

                    [$tplRating, $tplFmt] = $partCommentTemplates[$tplCursor % count($partCommentTemplates)];
                    $shortTitle = Str::limit($partItem->title, 68, '');
                    $shopDisplay = $pShop->display_branch_tag
                        ? "{$pShop->name} ({$pShop->display_branch_tag})"
                        : $pShop->name;
                    $commentText = substr_count($tplFmt, '%s') === 2
                        ? sprintf($tplFmt, $shortTitle, $shopDisplay)
                        : sprintf($tplFmt, $shortTitle);

                    $revTime = $completedAt->copy()->addHours(2);
                    $rowsToInsert[] = [
                        'repair_shop_id' => $pShop->id,
                        'customer_id' => $chosenCustomer->id,
                        'repair_request_id' => $spReq->id,
                        'spare_part_listing_id' => $partItem->id,
                        'rating' => $tplRating,
                        'service_provided' => Str::limit('Spare Part — ' . $partItem->title, 175, ''),
                        'review_comment' => $commentText,
                        'is_verified_purchase' => true,
                        'is_hidden' => false,
                        'moderated_at' => null,
                        'moderated_by_id' => null,
                        'moderation_note' => null,
                        'created_at' => $revTime,
                        'updated_at' => $revTime,
                    ];

                    $addedForShop++;
                    $tplCursor++;
                }
            }
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
