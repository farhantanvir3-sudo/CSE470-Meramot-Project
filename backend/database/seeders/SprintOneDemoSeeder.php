<?php

namespace Database\Seeders;

use App\Models\AIChatMessage;
use App\Models\AIChatSession;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\LocationLog;
use App\Models\ModerationLog;
use App\Models\PaymentTransaction;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\RiderChatMessage;
use App\Models\RiderEarnings;
use App\Models\ServicePackage;
use App\Models\ShopReview;
use App\Models\User;
use App\Models\VendorPayout;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * ============================================================================
 * SPRINT 1 LIVE DEMO SEEDER — 100% Interconnected End-to-End Workflows
 * ============================================================================
 * Author: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Purpose:
 *   Seeds coherent, 100% relationally interconnected live workflows across
 *   Admin, Vendor, Customer, and Courier screens:
 *     1. Pending & Rejected Vendor Applications (R11) with `shop_staff`,
 *        admin review audit metadata, and zero ratings/reviews before approval.
 *     2. Live Marketplace Request in `BIDDING` connected to an `AIChatSession`
 *        (`ai_summary`), competing `Bid`s, and `NotificationLog` (R7/R10/R12/R14).
 *     3. Live Bench Job in `WAITING_APPROVAL` (Quote 2) connected to inbound
 *        `DeliveryJob`, `LocationLog`, `RiderEarnings`, `NotificationLog`, and
 *        an open `SupportTicket` + `SupportMessage` thread (R7/R4/R14).
 *     4. Live Direct Service Cart Order in `PENDING` connected to `ServicePackage`
 *        (`order_count` synced), `Cart` (`CHECKED_OUT`), `RepairJob`, `PaymentTransaction`,
 *        and unassigned `DeliveryJob` in Rider Queue (R8/R2).
 *     5. Live `IN_TRANSIT` Courier Job assigned to `Rahim Uddin` with real-time
 *        `LocationLog` GPS breadcrumbs and `RiderChatMessage`s for `/tracking/{id}` (R2/R4/R5).
 *     6. Referentially valid `ModerationLog` & `AuditLog` entries pointing to
 *        actual hidden `ShopReview`, rejected `VendorProfile`/`DeliveryAgent`,
 *        disbursed `VendorPayout`, and resolved `DisputeCase` rows (R19/R20).
 * ============================================================================
 */
class SprintOneDemoSeeder extends Seeder
{
    public function run(): void
    {
        $passwordHash = Hash::make('password123');
        $now = now();

        $meramotAdmin = User::where('email', 'admin@meramot.demo')->first()
            ?? User::where('role', 'admin')->orderBy('id')->first();
        $logisticsAdmin = User::where('email', 'delivery.admin@meramot.demo')->first()
            ?? $meramotAdmin;

        /* --------------------------------------------------------------------
         * 1. Feature R11: Pending & Rejected Vendor Applications for Admin Desk
         *    (Strictly 0.00 rating, 0 reviews, hidden & non-bookable before approval!)
         * -------------------------------------------------------------------- */
        $pendingVendorUser1 = User::updateOrCreate(
            ['email' => 'electrobench.applicant@meramot.demo'],
            [
                'name' => 'Tanvir Ahmed (ElectroBench Applicant)',
                'username' => 'electrobench_applicant',
                'password' => $passwordHash,
                'phone' => '+8801715001122',
                'role' => 'vendor',
                'is_active' => true,
            ]
        );

        $pendingProfile1 = VendorProfile::updateOrCreate(
            ['user_id' => $pendingVendorUser1->id],
            [
                'shop_name' => 'ElectroBench Diagnostics',
                'brand_name' => 'ElectroBench Diagnostics',
                'is_branched' => false,
                'business_license_no' => 'TRAD/DSCC/2026-44109',
                'nid_number' => '1994269810234',
                'verification_status' => 'pending',
                'rejection_reason' => null,
                'rejected_at' => null,
                'reviewed_by_admin_id' => null,
                'reviewed_at' => null,
                'gps_address' => 'Near Road 27 Circle, Dhanmondi, Dhaka',
                'same_as_gps_location' => false,
                'shop_address' => 'Level 4, Suite 408, Orchard Point, Dhanmondi, Dhaka',
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'shop_latitude' => 23.7465,
                'shop_longitude' => 90.3760,
                'categories' => ['Laptop', 'Phone', 'Console'],
                'courier_pickup' => true,
                'rating' => 0.00,
            ]
        );

        $pendingShop1 = RepairShop::updateOrCreate(
            ['slug' => 'electrobench-diagnostics'],
            [
                'vendor_profile_id' => $pendingProfile1->id,
                'name' => 'ElectroBench Diagnostics',
                'is_branch' => false,
                'branch_name' => null,
                'description' => 'Component-level motherboard diagnostics & BGA rework lab awaiting Admin Trade License verification.',
                'gps_address' => 'Near Road 27 Circle, Dhanmondi, Dhaka',
                'same_as_gps_location' => false,
                'address' => 'Level 4, Suite 408, Orchard Point, Dhanmondi, Dhaka',
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'latitude' => 23.7465,
                'longitude' => 90.3760,
                'phone' => '+8801715001122',
                'email' => 'electrobench.applicant@meramot.demo',
                'rating' => 0.00,
                'review_count' => 0,
                'is_featured' => false,
                'is_active' => true,
                'is_public' => false,
                'setup_complete' => false,
                'accepts_direct_orders' => false,
                'supports_pickup' => true,
                'delivery_radius_km' => 12.00,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => [
                    'Laptop Motherboard BGA Rework',
                    'Smartphone Short-Circuit Diagnostics',
                    'Gaming Console HDMI & Power Repair',
                ],
            ]
        );

        $pendingVendorUser2 = User::updateOrCreate(
            ['email' => 'ctg.microsolder@meramot.demo'],
            [
                'name' => 'Ariful Islam (Chattogram Applicant)',
                'username' => 'karnaphuli_applicant',
                'password' => $passwordHash,
                'phone' => '+8801819003344',
                'role' => 'vendor',
                'is_active' => true,
            ]
        );

        $pendingProfile2 = VendorProfile::updateOrCreate(
            ['user_id' => $pendingVendorUser2->id],
            [
                'shop_name' => 'Karnaphuli MicroSolder Lab',
                'brand_name' => 'Karnaphuli MicroSolder Lab',
                'is_branched' => false,
                'business_license_no' => 'TRAD/CCC/2026-88210',
                'nid_number' => '1996319820455',
                'verification_status' => 'pending',
                'rejection_reason' => null,
                'rejected_at' => null,
                'reviewed_by_admin_id' => null,
                'reviewed_at' => null,
                'gps_address' => 'GEC Circle Main Point, Chattogram',
                'same_as_gps_location' => true,
                'shop_address' => 'GEC Circle Main Point, Chattogram',
                'city' => 'Chattogram',
                'area' => 'GEC Circle',
                'shop_latitude' => 22.3590,
                'shop_longitude' => 91.8212,
                'categories' => ['Phone', 'Tablet'],
                'courier_pickup' => true,
                'rating' => 0.00,
            ]
        );

        $pendingShop2 = RepairShop::updateOrCreate(
            ['slug' => 'karnaphuli-microsolder-lab'],
            [
                'vendor_profile_id' => $pendingProfile2->id,
                'name' => 'Karnaphuli MicroSolder Lab',
                'is_branch' => false,
                'branch_name' => null,
                'description' => 'Smartphone OLED flex bonding & IC repair workshop in Chattogram awaiting Admin approval.',
                'gps_address' => 'GEC Circle Main Point, Chattogram',
                'same_as_gps_location' => true,
                'address' => 'GEC Circle Main Point, Chattogram',
                'city' => 'Chattogram',
                'area' => 'GEC Circle',
                'latitude' => 22.3590,
                'longitude' => 91.8212,
                'phone' => '+8801819003344',
                'email' => 'ctg.microsolder@meramot.demo',
                'rating' => 0.00,
                'review_count' => 0,
                'is_featured' => false,
                'is_active' => true,
                'is_public' => false,
                'setup_complete' => false,
                'accepts_direct_orders' => false,
                'supports_pickup' => true,
                'delivery_radius_km' => 10.00,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => [
                    'Smartphone OLED Flex Bonding',
                    'Tablet Touch Digitizer & IC Repair',
                ],
            ]
        );

        $rejectedVendorUser = User::updateOrCreate(
            ['email' => 'unverified.corner@meramot.demo'],
            [
                'name' => 'Rashed Mia (Rejected Applicant)',
                'username' => 'quickpatch_applicant',
                'password' => $passwordHash,
                'phone' => '+8801914005566',
                'role' => 'vendor',
                'is_active' => true,
            ]
        );

        $rejectedAt = $now->copy()->subDays(2);
        $rejectedProfile = VendorProfile::updateOrCreate(
            ['user_id' => $rejectedVendorUser->id],
            [
                'shop_name' => 'QuickPatch Kiosk',
                'brand_name' => 'QuickPatch Kiosk',
                'is_branched' => false,
                'business_license_no' => 'EXPIRED-2021-0091',
                'nid_number' => '1999112233445',
                'verification_status' => 'rejected',
                'rejection_reason' => 'Trade license expired in 2021; please upload renewed City Corporation certificate.',
                'rejected_at' => $rejectedAt,
                'reviewed_by_admin_id' => $meramotAdmin?->id,
                'reviewed_at' => $rejectedAt,
                'gps_address' => 'Farmgate Footover Market, Dhaka',
                'same_as_gps_location' => true,
                'shop_address' => 'Farmgate Footover Market, Dhaka',
                'city' => 'Dhaka',
                'area' => 'Farmgate',
                'shop_latitude' => 23.7561,
                'shop_longitude' => 90.3872,
                'categories' => ['Phone'],
                'courier_pickup' => false,
                'rating' => 0.00,
            ]
        );

        $rejectedShop = RepairShop::updateOrCreate(
            ['slug' => 'quickpatch-kiosk'],
            [
                'vendor_profile_id' => $rejectedProfile->id,
                'name' => 'QuickPatch Kiosk',
                'is_branch' => false,
                'branch_name' => null,
                'description' => 'Unverified kiosk application declined during compliance audit.',
                'gps_address' => 'Farmgate Footover Market, Dhaka',
                'same_as_gps_location' => true,
                'address' => 'Farmgate Footover Market, Dhaka',
                'city' => 'Dhaka',
                'area' => 'Farmgate',
                'latitude' => 23.7561,
                'longitude' => 90.3872,
                'phone' => '+8801914005566',
                'email' => 'unverified.corner@meramot.demo',
                'rating' => 0.00,
                'review_count' => 0,
                'is_featured' => false,
                'is_active' => false,
                'is_public' => false,
                'setup_complete' => false,
                'accepts_direct_orders' => false,
                'supports_pickup' => false,
                'delivery_radius_km' => 5.00,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['Basic Phone Screen Swap'],
            ]
        );

        // Link `shop_staff` OWNER records for applicant workshops as well
        foreach ([
            [$pendingShop1->id, $pendingVendorUser1->id, true],
            [$pendingShop2->id, $pendingVendorUser2->id, true],
            [$rejectedShop->id, $rejectedVendorUser->id, false],
        ] as [$sId, $uId, $activeFlag]) {
            DB::table('shop_staff')->updateOrInsert(
                ['shop_id' => $sId, 'user_id' => $uId],
                [
                    'role' => 'OWNER',
                    'is_active' => $activeFlag,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        // Guarantee un-onboarded shops never carry reviews
        ShopReview::whereIn('repair_shop_id', [
            $pendingShop1->id,
            $pendingShop2->id,
            $rejectedShop->id,
        ])->delete();

        /* --------------------------------------------------------------------
         * 2. Live Customer, AI Triage, Bids, Logistics & Support Interconnection
         * -------------------------------------------------------------------- */
        $customer = User::where('email', 'customer@meramot.demo')->first();
        if (!$customer) {
            return;
        }

        $nusratCustomer = User::where('email', 'nusrat.jahan@meramot.demo')->first() ?? $customer;

        $approvedRider = DeliveryAgent::with('user')
            ->whereHas('user', fn ($q) => $q->where('email', 'rider.demo@meramot.test'))
            ->first()
            ?? DeliveryAgent::with('user')
                ->where('registration_status', 'APPROVED')
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

        $bananiZoneId = DB::table('coverage_zones')->where('city', 'Dhaka')->where('area', 'Banani')->value('id');
        $gulshanZoneId = DB::table('coverage_zones')->where('city', 'Dhaka')->where('area', 'Gulshan')->value('id');
        $dhanmondiZoneId = DB::table('coverage_zones')->where('city', 'Dhaka')->where('area', 'Dhanmondi')->value('id');

        $protechMain = RepairShop::where('slug', 'protech-solutions')->where('is_public', true)->first();
        $dhakaDoctors = RepairShop::where('slug', 'dhaka-device-doctors')->where('is_public', true)->first();

        $competitorShops = RepairShop::where('is_public', true)
            ->when($protechMain, fn ($q) => $q->where('vendor_profile_id', '!=', $protechMain->vendor_profile_id))
            ->orderByDesc('is_featured')
            ->orderBy('id')
            ->take(2)
            ->get();

        // 2A. Interconnected AI Diagnostic Session -> Demo Request A (`BIDDING`)
        $aiSession = AIChatSession::updateOrCreate(
            [
                'user_id' => $customer->id,
                'title' => 'MacBook Pro 14 M2 Liquid Spill Diagnostic',
            ],
            [
                'device_category' => 'Laptop',
            ]
        );

        AIChatMessage::where('ai_chat_session_id', $aiSession->id)->delete();
        AIChatMessage::insert([
            [
                'ai_chat_session_id' => $aiSession->id,
                'role' => 'user',
                'text' => 'Coffee splashed near the left Thunderbolt ports of my MacBook Pro 14 M2. Boot chime plays and Caps Lock lights up, but screen backlight stays completely dark.',
                'created_at' => $now->copy()->subHours(4)->subMinutes(20),
                'updated_at' => $now->copy()->subHours(4)->subMinutes(20),
            ],
            [
                'ai_chat_session_id' => $aiSession->id,
                'role' => 'assistant',
                'text' => 'Probable Cause: Liquid ingress shorted the PPVOUT_S0_LCDBKLT backlight boost circuit or LP8550 driver fuse while main SoC rails remain intact. Power off immediately, avoid charging, and book ultrasonic board cleaning + backlight rail micro-soldering (est. ৳4,500–৳6,000).',
                'created_at' => $now->copy()->subHours(4)->subMinutes(19),
                'updated_at' => $now->copy()->subHours(4)->subMinutes(19),
            ],
        ]);

        $openBiddingReq = RepairRequest::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'title' => 'Apple MacBook Pro 14 M2 — Liquid Spill & No Backlight',
            ],
            [
                'source' => 'MARKETPLACE',
                'device_type' => 'Laptop',
                'brand' => 'Apple',
                'model_name' => 'MacBook Pro 14 M2 (A2779)',
                'issue_category' => 'Water Damage',
                'description' => 'Coffee splashed near left Thunderbolt ports; chime plays on power-on, trackpad clicks, but Liquid Retina XDR backlight stays dark.',
                'problem' => "Coffee splashed near left Thunderbolt ports; chime plays on power-on, trackpad clicks, but Liquid Retina XDR backlight stays dark.\n\n[Location: Banani, Dhaka]\n[Year: 2023] [Specs: M2 Pro • 16GB RAM • 512GB SSD] [Customer Budget: ৳6500]",
                'ai_summary' => "AI Diagnostic Session #{$aiSession->id}: Likely PPVOUT_S0_LCDBKLT backlight rail / LP8550 driver short from liquid ingress; main M2 logic rails intact. Recommended ultrasonic bath + microscope fuse/IC restoration.",
                'mode' => 'CHECKUP_AND_REPAIR',
                'status' => 'BIDDING',
                'preferred_pickup' => true,
                'preferred_service_type' => 'pickup_dropoff',
                'delivery_type' => 'EXPRESS',
                'contact_phone' => '+8801700000000',
                'pickup_address' => 'House 18, Road 11, Banani, Dhaka 1213',
                'dropoff_address' => 'House 18, Road 11, Banani, Dhaka 1213',
            ]
        );

        if ($competitorShops->count() >= 1) {
            Bid::updateOrCreate(
                [
                    'repair_request_id' => $openBiddingReq->id,
                    'shop_id' => $competitorShops[0]->id,
                ],
                [
                    'parts_cost' => 3200.00,
                    'labor_cost' => 1400.00,
                    'proposed_cost' => 4600.00,
                    'estimated_days' => 2,
                    'warranty_days' => 90,
                    'line_items' => [
                        ['item' => 'LP8550 Backlight Driver IC & Fuse', 'cost' => 3200],
                        ['item' => 'Ultrasonic Board Cleaning & Labor', 'cost' => 1400],
                    ],
                    'technician_note' => 'Ultrasonic board bath + LP8550 backlight driver IC & fuse replacement under stereo microscope.',
                    'status' => 'ACTIVE',
                ]
            );
        }

        if ($competitorShops->count() >= 2) {
            Bid::updateOrCreate(
                [
                    'repair_request_id' => $openBiddingReq->id,
                    'shop_id' => $competitorShops[1]->id,
                ],
                [
                    'parts_cost' => 3800.00,
                    'labor_cost' => 1500.00,
                    'proposed_cost' => 5300.00,
                    'estimated_days' => 1,
                    'warranty_days' => 120,
                    'line_items' => [
                        ['item' => 'Display Backlight Flex & Filter Rail', 'cost' => 3800],
                        ['item' => 'Same-Day Express Ultrasonic Restoration', 'cost' => 1500],
                    ],
                    'technician_note' => 'Same-day express ultrasonic cleaning + genuine Apple display flex & backlight rail restoration.',
                    'status' => 'ACTIVE',
                ]
            );
        }

        DB::table('notification_logs')->updateOrInsert(
            [
                'repair_request_id' => $openBiddingReq->id,
                'channel' => 'EMAIL',
                'recipient' => $customer->email,
            ],
            [
                'delivery_job_id' => null,
                'subject' => '2 Verified Technician Bids Received on Your MacBook Pro 14 M2 Request',
                'body' => 'Two verified workshops submitted itemized Quote 1 bids starting from ৳4,600. Compare parts & warranty terms on Meramot.',
                'provider' => 'Resend',
                'provider_ref' => 'msg_mrm_bidding_' . $openBiddingReq->id,
                'status' => 'SENT',
                'sent_at' => $now->copy()->subHours(2),
                'created_at' => $now->copy()->subHours(2),
                'updated_at' => $now->copy()->subHours(2),
            ]
        );

        // 2B. Demo Request B: Bench Inspection Completed — Waiting for Customer Quote 2 Approval
        if ($protechMain) {
            $quoteTwoReq = RepairRequest::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'title' => 'Samsung Galaxy S23 Ultra — Green Line & Touch Flicker',
                ],
                [
                    'source' => 'MARKETPLACE',
                    'requested_shop_id' => $protechMain->id,
                    'device_type' => 'Phone',
                    'brand' => 'Samsung',
                    'model_name' => 'Galaxy S23 Ultra',
                    'issue_category' => 'Screen / Display',
                    'description' => 'Vertical green line appeared after drop; intermittent touch freeze on upper-right edge.',
                    'problem' => "Vertical green line appeared after drop; intermittent touch freeze on upper-right edge.\n\n[Location: Gulshan, Dhaka]",
                    'mode' => 'CHECKUP_AND_REPAIR',
                    'status' => 'WAITING_APPROVAL',
                    'preferred_pickup' => true,
                    'preferred_service_type' => 'pickup_dropoff',
                    'delivery_type' => 'REGULAR',
                    'contact_phone' => '+8801700000000',
                    'pickup_address' => 'Road 35, Gulshan 2, Dhaka 1212',
                    'dropoff_address' => 'Road 35, Gulshan 2, Dhaka 1212',
                    'quoted_final_amount' => 8900.00,
                    'approved_at' => $now->copy()->subHours(7),
                ]
            );

            $acceptedBid = Bid::updateOrCreate(
                [
                    'repair_request_id' => $quoteTwoReq->id,
                    'shop_id' => $protechMain->id,
                ],
                [
                    'parts_cost' => 7000.00,
                    'labor_cost' => 1500.00,
                    'proposed_cost' => 8500.00,
                    'estimated_days' => 2,
                    'warranty_days' => 90,
                    'line_items' => [
                        ['item' => 'AMOLED Flex Bonding Estimate', 'cost' => 7000],
                        ['item' => 'Cleanroom Diagnostics & Labor', 'cost' => 1500],
                    ],
                    'technician_note' => 'Initial Quote 1 for AMOLED flex bonding & display assembly check.',
                    'status' => 'ACCEPTED',
                ]
            );

            $quoteTwoReq->update(['selected_bid_id' => $acceptedBid->id]);

            $quoteTwoJob = RepairJob::updateOrCreate(
                ['repair_request_id' => $quoteTwoReq->id],
                [
                    'shop_id' => $protechMain->id,
                    'accepted_bid_id' => $acceptedBid->id,
                    'status' => 'WAITING_APPROVAL',
                    'diagnosis_notes' => 'Bench microscope inspection confirmed AMOLED flex trace burn + PMIC display rail filter damage. Final itemized Quote 2 includes flex laser bonding + filter IC.',
                    'final_quoted_amount' => 8900.00,
                    'final_quote_items' => [
                        ['title' => 'AMOLED Flex Laser Bonding & Filter IC', 'amount' => 7400],
                        ['title' => 'Cleanroom Calibration & Waterproof Reseal', 'amount' => 1500],
                    ],
                    'customer_approved' => null,
                    'started_at' => $now->copy()->subHours(5),
                ]
            );

            if ($approvedRider) {
                $inboundDelivery = DeliveryJob::updateOrCreate(
                    [
                        'repair_request_id' => $quoteTwoReq->id,
                        'direction' => 'TO_SHOP',
                    ],
                    [
                        'repair_job_id' => $quoteTwoJob->id,
                        'delivery_agent_id' => $approvedRider->id,
                        'coverage_zone_id' => $gulshanZoneId,
                        'type' => 'REGULAR',
                        'status' => 'DELIVERED',
                        'tracking_code' => 'MRM-DLV-2001',
                        'rider_name' => $approvedRider->user?->name ?? 'Rahim Uddin',
                        'rider_phone' => $approvedRider->user?->phone ?? '+8801799998888',
                        'pickup_address' => 'Road 35, Gulshan 2, Dhaka 1212',
                        'dropoff_address' => $protechMain->public_address,
                        'pickup_otp' => '5142',
                        'dropoff_otp' => '9384',
                        'distance_km' => 3.20,
                        'fee' => 150.00,
                        'scheduled_at' => $now->copy()->subHours(6)->subMinutes(45),
                        'dispatched_at' => $now->copy()->subHours(6)->subMinutes(30),
                        'picked_up_at' => $now->copy()->subHours(6),
                        'delivered_at' => $now->copy()->subHours(5)->subMinutes(20),
                    ]
                );

                LocationLog::where('delivery_job_id', $inboundDelivery->id)->delete();
                LocationLog::create([
                    'delivery_agent_id' => $approvedRider->id,
                    'delivery_job_id' => $inboundDelivery->id,
                    'latitude' => (float) $protechMain->latitude,
                    'longitude' => (float) $protechMain->longitude,
                    'recorded_at' => $now->copy()->subHours(5)->subMinutes(20),
                ]);

                RiderEarnings::updateOrCreate(
                    [
                        'delivery_agent_id' => $approvedRider->id,
                        'delivery_job_id' => $inboundDelivery->id,
                    ],
                    [
                        'trip_fee' => 150.00,
                        'bonus_amount' => 0.00,
                        'total_earned' => 150.00,
                        'payout_status' => 'unpaid',
                        'payout_reference' => null,
                    ]
                );

                DB::table('notification_logs')->updateOrInsert(
                    [
                        'repair_request_id' => $quoteTwoReq->id,
                        'channel' => 'SMS',
                        'recipient' => '+8801700000000',
                    ],
                    [
                        'delivery_job_id' => $inboundDelivery->id,
                        'subject' => 'Final Bench Quote 2 Ready for Approval',
                        'body' => "Meramot: ProTech Solutions completed bench diagnostics on your Galaxy S23 Ultra (Quote 2: ৳8,900). Approve or decline in your tracker.",
                        'provider' => 'BulkSMSBD',
                        'provider_ref' => 'sms_mrm_q2_' . $quoteTwoReq->id,
                        'status' => 'SENT',
                        'sent_at' => $now->copy()->subHours(4),
                        'created_at' => $now->copy()->subHours(4),
                        'updated_at' => $now->copy()->subHours(4),
                    ]
                );
            }

            // Interconnected Support Ticket + Messages referencing Demo Request B & ProTech Solutions
            if ($meramotAdmin) {
                DB::table('support_tickets')->updateOrInsert(
                    [
                        'user_id' => $customer->id,
                        'repair_request_id' => $quoteTwoReq->id,
                    ],
                    [
                        'shop_id' => $protechMain->id,
                        'repair_job_id' => $quoteTwoJob->id,
                        'assigned_admin_id' => $meramotAdmin->id,
                        'subject' => 'Question regarding IP68 waterproof gasket reseal on Quote 2',
                        'message' => 'Does the ৳8,900 final bench quote for my Galaxy S23 Ultra include factory adhesive gasket replacement?',
                        'status' => 'IN_PROGRESS',
                        'priority' => 'normal',
                        'admin_notes' => 'Confirmed with ProTech Solutions Banani bench lead — OEM pre-cut IP68 rework gasket is included in line item #2.',
                        'created_at' => $now->copy()->subHours(3),
                        'updated_at' => $now->copy()->subHours(2),
                    ]
                );

                $ticketId = DB::table('support_tickets')
                    ->where('user_id', $customer->id)
                    ->where('repair_request_id', $quoteTwoReq->id)
                    ->value('id');

                if ($ticketId) {
                    DB::table('support_messages')->where('ticket_id', $ticketId)->delete();
                    DB::table('support_messages')->insert([
                        [
                            'ticket_id' => $ticketId,
                            'author_id' => $customer->id,
                            'sender_type' => 'user',
                            'message' => 'Hi Meramot team, before I click Approve on Quote 2 (৳8,900), can you confirm whether the waterproof frame seal is replaced?',
                            'attachment_urls' => null,
                            'created_at' => $now->copy()->subHours(3),
                            'updated_at' => $now->copy()->subHours(3),
                        ],
                        [
                            'ticket_id' => $ticketId,
                            'author_id' => $meramotAdmin->id,
                            'sender_type' => 'admin',
                            'message' => 'Confirmed with ProTech Solutions [Banani DOHS Flagship]: Cleanroom Calibration & Waterproof Reseal (৳1,500) includes a brand-new Samsung IP68 frame adhesive kit.',
                            'attachment_urls' => null,
                            'created_at' => $now->copy()->subHours(2),
                            'updated_at' => $now->copy()->subHours(2),
                        ],
                    ]);
                }
            }

            // 2C. Demo Request C: Incoming Direct-Service Cart Order for ProTech Solutions (`PENDING`)
            //     Interconnected with `ServicePackage` (`order_count`), `Cart` (`CHECKED_OUT`), `RepairJob`, `PaymentTransaction` & `DeliveryJob`!
            $directPkg = ServicePackage::where('title', 'Laptop High-Capacity Battery Replacement')->first()
                ?? ServicePackage::first();
            $directGross = $directPkg ? (float) $directPkg->fixed_price : 3200.00;

            $alreadySeededDirect = RepairRequest::where('customer_id', $customer->id)
                ->where('title', 'Direct Service Order — Laptop High-Capacity Battery Replacement')
                ->exists();

            $directCart = Cart::updateOrCreate(
                [
                    'user_id' => $customer->id,
                    'shop_id' => $protechMain->id,
                    'status' => 'CHECKED_OUT',
                ],
                [
                    'updated_at' => $now->copy()->subMinutes(45),
                ]
            );

            CartItem::where('cart_id', $directCart->id)->delete();
            CartItem::create([
                'cart_id' => $directCart->id,
                'service_name' => $directPkg ? $directPkg->title : 'Laptop High-Capacity Battery Replacement',
                'description' => 'Direct fixed-price package booking via Quickmatch Service Cart',
                'price' => $directGross,
                'quantity' => 1,
                'metadata' => [
                    'package_id' => $directPkg?->id,
                    'source' => 'QUICKMATCH_PACKAGE',
                ],
            ]);

            if (!$alreadySeededDirect && $directPkg) {
                $directPkg->increment('order_count');
            }

            $directOrderReq = RepairRequest::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'title' => 'Direct Service Order — Laptop High-Capacity Battery Replacement',
                ],
                [
                    'source' => 'DIRECT_SERVICE',
                    'requested_shop_id' => $protechMain->id,
                    'requested_service_id' => $directPkg?->id,
                    'device_type' => 'Laptop',
                    'brand' => 'Lenovo',
                    'model_name' => 'ThinkPad T14 Gen 3',
                    'issue_category' => 'Fixed-Price Package Booking',
                    'description' => 'Direct fixed-price package booking placed via Quickmatch Service Cart.',
                    'problem' => "[Direct Service Cart Booking]\n• " . ($directPkg ? $directPkg->title : 'Laptop High-Capacity Battery Replacement') . " x1 — ৳" . number_format($directGross, 0) . "\n\nCustomer Notes: Please pick up between 2 PM and 5 PM from Banani Road 11.",
                    'mode' => 'DIRECT_REPAIR',
                    'status' => 'PENDING',
                    'preferred_pickup' => true,
                    'preferred_service_type' => 'pickup_dropoff',
                    'delivery_type' => 'REGULAR',
                    'contact_phone' => '+8801700000000',
                    'pickup_address' => 'House 18, Road 11, Banani, Dhaka 1213',
                    'dropoff_address' => $protechMain->public_address,
                    'quoted_final_amount' => $directGross,
                ]
            );

            $directRepairJob = RepairJob::updateOrCreate(
                ['repair_request_id' => $directOrderReq->id],
                [
                    'shop_id' => $protechMain->id,
                    'status' => 'CREATED',
                    'final_quoted_amount' => $directGross,
                    'final_quote_items' => [
                        [
                            'title' => $directPkg ? $directPkg->title : 'Laptop High-Capacity Battery Replacement',
                            'amount' => $directGross,
                        ],
                    ],
                ]
            );

            PaymentTransaction::updateOrCreate(
                ['transaction_reference' => 'TRX-MRM-DIRECT-DEMO1'],
                [
                    'user_id' => $customer->id,
                    'repair_request_id' => $directOrderReq->id,
                    'amount' => $directGross,
                    'currency' => 'BDT',
                    'payment_method' => 'CASH',
                    'status' => 'PENDING',
                    'paid_at' => null,
                ]
            );

            DeliveryJob::updateOrCreate(
                [
                    'repair_request_id' => $directOrderReq->id,
                    'direction' => 'TO_SHOP',
                ],
                [
                    'repair_job_id' => $directRepairJob->id,
                    'delivery_agent_id' => null,
                    'coverage_zone_id' => $bananiZoneId,
                    'type' => 'REGULAR',
                    'status' => 'PENDING',
                    'tracking_code' => 'MRM-DLV-2002',
                    'pickup_address' => 'House 18, Road 11, Banani, Dhaka 1213',
                    'dropoff_address' => $protechMain->public_address,
                    'pickup_otp' => '6420',
                    'dropoff_otp' => '7195',
                    'distance_km' => 1.90,
                    'fee' => 150.00,
                    'scheduled_at' => $now->copy()->addHours(2),
                ]
            );
        }

        // 2D. Demo Request D: Active `IN_TRANSIT` Courier Pickup for `Dhaka Device Doctors`
        //     Populates Rider Active Jobs (`/delivery/jobs-queue`), Live Map (`/tracking/{id}`) & `rider_chat_messages`!
        if ($dhakaDoctors && $approvedRider && $approvedRider->user && $logisticsAdmin) {
            $transitReq = RepairRequest::updateOrCreate(
                [
                    'customer_id' => $nusratCustomer->id,
                    'title' => 'Apple iPad Pro 11 M2 — Cracked Digitizer & Touch Drift',
                ],
                [
                    'source' => 'MARKETPLACE',
                    'requested_shop_id' => $dhakaDoctors->id,
                    'device_type' => 'Tablet',
                    'brand' => 'Apple',
                    'model_name' => 'iPad Pro 11 M2',
                    'issue_category' => 'Screen / Display',
                    'description' => 'Corner impact cracked front glass; Apple Pencil hover skips near center.',
                    'problem' => 'Corner impact cracked front glass; courier currently en route to Dhaka Device Doctors bench.',
                    'mode' => 'CHECKUP_AND_REPAIR',
                    'status' => 'PICKED_UP',
                    'preferred_pickup' => true,
                    'preferred_service_type' => 'pickup_dropoff',
                    'delivery_type' => 'EXPRESS',
                    'contact_phone' => $nusratCustomer->phone ?? '+8801714100201',
                    'pickup_address' => 'House 22, Road 27, Dhanmondi, Dhaka 1209',
                    'dropoff_address' => $dhakaDoctors->public_address,
                    'quoted_final_amount' => 5400.00,
                    'approved_at' => $now->copy()->subMinutes(50),
                ]
            );

            $transitBid = Bid::updateOrCreate(
                [
                    'repair_request_id' => $transitReq->id,
                    'shop_id' => $dhakaDoctors->id,
                ],
                [
                    'parts_cost' => 4100.00,
                    'labor_cost' => 1300.00,
                    'proposed_cost' => 5400.00,
                    'estimated_days' => 1,
                    'warranty_days' => 90,
                    'line_items' => [
                        ['item' => 'iPad Pro 11 M2 Digitizer & OCA Lamination', 'cost' => 4100],
                        ['item' => 'ProMotion Touch Calibration & Labor', 'cost' => 1300],
                    ],
                    'technician_note' => 'Cleanroom OCA vacuum lamination preserving original Liquid Retina panel.',
                    'status' => 'ACCEPTED',
                ]
            );

            $transitReq->update(['selected_bid_id' => $transitBid->id]);

            $transitRepairJob = RepairJob::updateOrCreate(
                ['repair_request_id' => $transitReq->id],
                [
                    'shop_id' => $dhakaDoctors->id,
                    'accepted_bid_id' => $transitBid->id,
                    'status' => 'PICKED_UP',
                    'final_quoted_amount' => 5400.00,
                    'customer_approved' => null,
                ]
            );

            $activeTransitDelivery = DeliveryJob::updateOrCreate(
                [
                    'repair_request_id' => $transitReq->id,
                    'direction' => 'TO_SHOP',
                ],
                [
                    'repair_job_id' => $transitRepairJob->id,
                    'delivery_agent_id' => $approvedRider->id,
                    'coverage_zone_id' => $dhanmondiZoneId,
                    'type' => 'EXPRESS',
                    'status' => 'IN_TRANSIT',
                    'tracking_code' => 'MRM-DLV-2003',
                    'rider_name' => $approvedRider->user->name,
                    'rider_phone' => $approvedRider->user->phone ?? '+8801799998888',
                    'pickup_address' => 'House 22, Road 27, Dhanmondi, Dhaka 1209',
                    'dropoff_address' => $dhakaDoctors->public_address,
                    'pickup_otp' => '3819',
                    'dropoff_otp' => '8472',
                    'distance_km' => 1.80,
                    'fee' => 150.00,
                    'scheduled_at' => $now->copy()->subMinutes(40),
                    'dispatched_at' => $now->copy()->subMinutes(30),
                    'picked_up_at' => $now->copy()->subMinutes(15),
                    'delivered_at' => null,
                ]
            );

            LocationLog::where('delivery_job_id', $activeTransitDelivery->id)->delete();
            LocationLog::insert([
                [
                    'delivery_agent_id' => $approvedRider->id,
                    'delivery_job_id' => $activeTransitDelivery->id,
                    'latitude' => 23.75120000,
                    'longitude' => 90.37890000,
                    'recorded_at' => $now->copy()->subMinutes(14),
                    'created_at' => $now->copy()->subMinutes(14),
                    'updated_at' => $now->copy()->subMinutes(14),
                ],
                [
                    'delivery_agent_id' => $approvedRider->id,
                    'delivery_job_id' => $activeTransitDelivery->id,
                    'latitude' => 23.74840000,
                    'longitude' => 90.37620000,
                    'recorded_at' => $now->copy()->subMinutes(7),
                    'created_at' => $now->copy()->subMinutes(7),
                    'updated_at' => $now->copy()->subMinutes(7),
                ],
                [
                    'delivery_agent_id' => $approvedRider->id,
                    'delivery_job_id' => $activeTransitDelivery->id,
                    'latitude' => 23.74690000,
                    'longitude' => 90.37480000,
                    'recorded_at' => $now->copy()->subMinutes(2),
                    'created_at' => $now->copy()->subMinutes(2),
                    'updated_at' => $now->copy()->subMinutes(2),
                ],
            ]);

            $approvedRider->update([
                'current_latitude' => 23.74690000,
                'current_longitude' => 90.37480000,
            ]);

            RiderChatMessage::where('delivery_job_id', $activeTransitDelivery->id)->delete();
            RiderChatMessage::insert([
                [
                    'delivery_job_id' => $activeTransitDelivery->id,
                    'sender_user_id' => $approvedRider->user->id,
                    'sender_role' => 'DELIVERY',
                    'recipient_user_id' => $logisticsAdmin->id,
                    'message' => 'Picked up iPad Pro 11 M2 in sealed ESD bubble sleeve (OTP 3819 verified). Approaching Dhanmondi Road 11/A.',
                    'created_at' => $now->copy()->subMinutes(12),
                    'updated_at' => $now->copy()->subMinutes(12),
                ],
                [
                    'delivery_job_id' => $activeTransitDelivery->id,
                    'sender_user_id' => $logisticsAdmin->id,
                    'sender_role' => 'ADMIN',
                    'recipient_user_id' => $approvedRider->user->id,
                    'message' => 'Copy that Rahim. Dhaka Device Doctors Suite 3B reception is ready for dropoff OTP 8472.',
                    'created_at' => $now->copy()->subMinutes(10),
                    'updated_at' => $now->copy()->subMinutes(10),
                ],
            ]);
        }

        // Re-sync all DeliveryAgent.total_earnings strictly with RiderEarnings rows
        foreach (DeliveryAgent::all() as $agent) {
            $agent->update([
                'total_earnings' => (float) RiderEarnings::where('delivery_agent_id', $agent->id)->sum('total_earned'),
            ]);
        }

        /* --------------------------------------------------------------------
         * 3. Features R19 & R20: Referentially Connected Moderation & Audit Logs
         * -------------------------------------------------------------------- */
        if ($meramotAdmin) {
            ModerationLog::query()->delete();

            $hiddenReview = ShopReview::where('is_hidden', true)->first();
            if ($hiddenReview) {
                ModerationLog::create([
                    'admin_id' => $meramotAdmin->id,
                    'target_type' => 'review',
                    'target_id' => $hiddenReview->id,
                    'violation_type' => 'promotional_spam_link',
                    'action_taken' => 'content_removed',
                    'reason' => 'Review contained external promotional referral URL; hidden from public rating aggregation.',
                ]);
            }

            ModerationLog::create([
                'admin_id' => $meramotAdmin->id,
                'target_type' => 'shop',
                'target_id' => $rejectedShop->id,
                'violation_type' => 'expired_trade_license',
                'action_taken' => 'suspended',
                'reason' => 'Storefront publication blocked due to expired 2021 Trade License certificate.',
            ]);

            AuditLog::query()->delete();

            AuditLog::create([
                'actor_id' => $meramotAdmin->id,
                'action' => 'vendor_rejected',
                'target_resource' => "vendor_profiles:{$rejectedProfile->id}",
                'payload_before' => ['verification_status' => 'pending', 'shop_name' => $rejectedProfile->shop_name],
                'payload_after' => ['verification_status' => 'rejected', 'rejection_reason' => $rejectedProfile->rejection_reason],
                'ip_address' => '103.148.72.14',
                'created_at' => $rejectedAt,
                'updated_at' => $rejectedAt,
            ]);

            if ($approvedRider) {
                AuditLog::create([
                    'actor_id' => $meramotAdmin->id,
                    'action' => 'delivery_agent_approved',
                    'target_resource' => "delivery_agents:{$approvedRider->id}",
                    'payload_before' => ['registration_status' => 'PENDING'],
                    'payload_after' => ['registration_status' => 'APPROVED', 'license_number' => $approvedRider->license_number],
                    'ip_address' => '103.148.72.14',
                    'created_at' => $now->copy()->subDays(35),
                    'updated_at' => $now->copy()->subDays(35),
                ]);
            }

            $rejectedRider = DeliveryAgent::where('registration_status', 'REJECTED')->first();
            if ($rejectedRider) {
                AuditLog::create([
                    'actor_id' => $meramotAdmin->id,
                    'action' => 'delivery_agent_rejected',
                    'target_resource' => "delivery_agents:{$rejectedRider->id}",
                    'payload_before' => ['registration_status' => 'PENDING'],
                    'payload_after' => ['registration_status' => 'REJECTED', 'rejection_reason' => $rejectedRider->rejection_reason],
                    'ip_address' => '103.148.72.14',
                    'created_at' => $now->copy()->subDays(10),
                    'updated_at' => $now->copy()->subDays(10),
                ]);
            }

            $paidPayout = VendorPayout::where('status', 'PAID')->first();
            if ($paidPayout) {
                AuditLog::create([
                    'actor_id' => $meramotAdmin->id,
                    'action' => 'payout_disbursed',
                    'target_resource' => "vendor_payouts:{$paidPayout->id}",
                    'payload_before' => ['status' => 'PENDING', 'reference_code' => $paidPayout->reference_code],
                    'payload_after' => ['status' => 'PAID', 'amount' => (float) $paidPayout->amount],
                    'ip_address' => '103.148.72.14',
                    'created_at' => $paidPayout->paid_at ?? $now->copy()->subDays(2),
                    'updated_at' => $paidPayout->paid_at ?? $now->copy()->subDays(2),
                ]);
            }

            $disputeRow = DB::table('dispute_cases')->first();
            if ($disputeRow) {
                AuditLog::create([
                    'actor_id' => $meramotAdmin->id,
                    'action' => 'dispute_refund_processed',
                    'target_resource' => "dispute_cases:{$disputeRow->id}",
                    'payload_before' => ['status' => 'OPEN'],
                    'payload_after' => ['status' => $disputeRow->status, 'refund_amount' => 500.00],
                    'ip_address' => '103.148.72.14',
                    'created_at' => $now->copy()->subDays(5),
                    'updated_at' => $now->copy()->subDays(5),
                ]);
            }
        }
    }
}
