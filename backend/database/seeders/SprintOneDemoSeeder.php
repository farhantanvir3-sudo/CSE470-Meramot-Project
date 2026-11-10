<?php

namespace Database\Seeders;

use App\Models\Bid;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ============================================================================
 * SPRINT 1 LIVE DEMO SEEDER — R11 Verification + R7/R8/R12/R15 End-to-End Flow
 * ============================================================================
 * Author: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Purpose:
 *   Seeds coherent, production-realistic Sprint 1 demo records so every single
 *   tab, desk, and lifecycle action works out-of-the-box during the Nov 12 demo:
 *     1. Pending & Rejected Vendor Onboarding applications for Admin Desk (R11),
 *        strictly obeying dual-address & branch-tag invariants.
 *     2. Live Marketplace Repair Request in BIDDING with competing bids (R7/R12).
 *     3. Live Bench Repair Job in WAITING_APPROVAL with Quote 2 itemization (R7).
 *     4. Live Direct Service Cart Order in PENDING for Vendor Direct Orders tab (R8).
 * ============================================================================
 */
class SprintOneDemoSeeder extends Seeder
{
    public function run(): void
    {
        $passwordHash = Hash::make('password123');

        /* --------------------------------------------------------------------
         * 1. Feature R11: Pending & Rejected Vendor Applications for Admin Desk
         * -------------------------------------------------------------------- */
        $pendingVendorUser1 = User::updateOrCreate(
            ['email' => 'electrobench.applicant@meramot.demo'],
            [
                'name' => 'Tanvir Ahmed (ElectroBench Applicant)',
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
                'gps_address' => 'Near Road 27 Circle, Dhanmondi, Dhaka',
                'same_as_gps_location' => false,
                'shop_address' => 'Level 4, Suite 408, Orchard Point, Dhanmondi, Dhaka',
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'shop_latitude' => 23.7465,
                'shop_longitude' => 90.3760,
                'categories' => ['Laptop', 'Phone', 'Console'],
                'courier_pickup' => true,
            ]
        );

        RepairShop::updateOrCreate(
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
                'rating' => 5.00,
                'review_count' => 0,
                'is_featured' => false,
                'is_active' => true,
                'is_public' => false, // Hidden from public explore until Admin approves (R11)
                'setup_complete' => true,
                'accepts_direct_orders' => true,
                'supports_pickup' => true,
                'delivery_radius_km' => 12.00,
                'categories' => ['Laptop', 'Phone', 'Console'],
                'specialties' => ['Laptop', 'Phone', 'Console'],
            ]
        );

        $pendingVendorUser2 = User::updateOrCreate(
            ['email' => 'ctg.microsolder@meramot.demo'],
            [
                'name' => 'Ariful Islam (Chattogram Applicant)',
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
                'gps_address' => 'GEC Circle Main Point, Chattogram',
                'same_as_gps_location' => true,
                'shop_address' => 'GEC Circle Main Point, Chattogram',
                'city' => 'Chattogram',
                'area' => 'GEC Circle',
                'shop_latitude' => 22.3590,
                'shop_longitude' => 91.8212,
                'categories' => ['Phone', 'Tablet'],
                'courier_pickup' => true,
            ]
        );

        RepairShop::updateOrCreate(
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
                'rating' => 5.00,
                'review_count' => 0,
                'is_featured' => false,
                'is_active' => true,
                'is_public' => false,
                'setup_complete' => true,
                'accepts_direct_orders' => true,
                'supports_pickup' => true,
                'delivery_radius_km' => 10.00,
                'categories' => ['Phone', 'Tablet'],
                'specialties' => ['Phone', 'Tablet'],
            ]
        );

        $rejectedVendorUser = User::updateOrCreate(
            ['email' => 'unverified.corner@meramot.demo'],
            [
                'name' => 'Rashed Mia (Rejected Applicant)',
                'password' => $passwordHash,
                'phone' => '+8801914005566',
                'role' => 'vendor',
                'is_active' => true,
            ]
        );

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
                'gps_address' => 'Farmgate Footover Market, Dhaka',
                'same_as_gps_location' => true,
                'shop_address' => 'Farmgate Footover Market, Dhaka',
                'city' => 'Dhaka',
                'area' => 'Farmgate',
                'shop_latitude' => 23.7561,
                'shop_longitude' => 90.3872,
                'categories' => ['Phone'],
                'courier_pickup' => false,
            ]
        );

        RepairShop::updateOrCreate(
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
                'rating' => 5.00,
                'review_count' => 0,
                'is_featured' => false,
                'is_active' => false,
                'is_public' => false,
                'setup_complete' => false,
                'accepts_direct_orders' => false,
                'supports_pickup' => false,
                'delivery_radius_km' => 5.00,
                'categories' => ['Phone'],
                'specialties' => ['Phone'],
            ]
        );

        /* --------------------------------------------------------------------
         * 2. Feature R7 / R8 / R12 / R15: Live Customer Repair Requests & Jobs
         * -------------------------------------------------------------------- */
        $customer = User::where('email', 'customer@meramot.demo')->first();
        if (!$customer) {
            return;
        }

        $protechMain = RepairShop::where('name', 'ProTech Solutions')
            ->where('is_public', true)
            ->orderBy('id')
            ->first();

        $competitorShops = RepairShop::where('is_public', true)
            ->when($protechMain, fn ($q) => $q->where('vendor_profile_id', '!=', $protechMain->vendor_profile_id))
            ->orderByDesc('is_featured')
            ->orderBy('id')
            ->take(2)
            ->get();

        // Demo Request A: Open Marketplace Request in BIDDING with 2 competing bids
        // (ProTech Solutions has NOT bid yet, so Vendor Demo can submit a live 3rd bid, OR Customer Demo can accept a bid immediately!)
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
                    'technician_note' => 'Same-day express ultrasonic cleaning + genuine Apple display flex & backlight rail restoration.',
                    'status' => 'ACTIVE',
                ]
            );
        }

        if ($protechMain) {
            // Demo Request B: Bench Inspection Completed — Waiting for Customer Quote 2 Approval
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
                    'technician_note' => 'Initial Quote 1 for AMOLED flex bonding & display assembly check.',
                    'status' => 'ACCEPTED',
                ]
            );

            $quoteTwoReq->update(['selected_bid_id' => $acceptedBid->id]);

            RepairJob::updateOrCreate(
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
                    'started_at' => now()->subHours(5),
                ]
            );

            // Demo Request C: Incoming Direct-Service Cart Order for ProTech Solutions (Ready to Accept/Decline!)
            RepairRequest::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'title' => 'Direct Service Order (2 items) — ProTech Solutions',
                ],
                [
                    'source' => 'DIRECT_SERVICE',
                    'requested_shop_id' => $protechMain->id,
                    'device_type' => 'Laptop',
                    'brand' => 'Lenovo',
                    'model_name' => 'ThinkPad T14 Gen 3',
                    'issue_category' => 'Fixed-Price Package Booking',
                    'description' => 'Direct fixed-price package booking placed via Quickmatch Service Cart.',
                    'problem' => "[Direct Service Cart Booking]\n• Laptop Deep Thermal Servicing & Ultrasonic Cleaning x1 — ৳1,800\n• NVMe SSD Health Check & OS Optimization x1 — ৳1,200\n\nCustomer Notes: Please pick up between 2 PM and 5 PM from Banani Road 11.",
                    'mode' => 'DIRECT_REPAIR',
                    'status' => 'PENDING',
                    'preferred_pickup' => true,
                    'preferred_service_type' => 'pickup_dropoff',
                    'delivery_type' => 'REGULAR',
                    'contact_phone' => '+8801700000000',
                    'pickup_address' => 'House 18, Road 11, Banani, Dhaka 1213',
                    'dropoff_address' => $protechMain->public_address,
                    'quoted_final_amount' => 3000.00,
                ]
            );
        }
    }
}
