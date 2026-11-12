<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgent;
use App\Models\FinancialLedger;
use App\Models\PaymentTransaction;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\User;
use App\Models\VendorProfile;

/**
 * ============================================================================
 * Meramot Unified Admin Console Hub Controller
 * ============================================================================
 * Author: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Connects all Sprint 1 Governance & Operations Desks:
 *   - Feature R11: Vendor Onboarding & Business Profile Verification (Farhan)
 *   - Feature R1:  Delivery Agent Onboarding & Verification (Md. Nafiul Islam)
 *   - Feature R16: Platform Financial Double-Entry Ledger (Md. Nawshad Zaman Khan)
 *   - Feature R6/R7: Nationwide Shop Directory & Live Repair Requests (Shanid)
 * ============================================================================
 */
class AdminDashboardController extends Controller
{
    public function index()
    {
        $kpis = [
            'total_users' => User::count(),
            'public_shops' => RepairShop::where('is_active', true)->where('is_public', true)->count(),
            'branched_brands' => VendorProfile::where('is_branched', true)->count(),
            'pending_vendors' => VendorProfile::whereIn('verification_status', ['pending', 'under_review'])->count(),
            'pending_riders' => DeliveryAgent::where('registration_status', 'PENDING')->count(),
            'approved_riders' => DeliveryAgent::where('registration_status', 'APPROVED')->count(),
            'open_requests' => RepairRequest::whereIn('status', ['PENDING', 'BIDDING', 'ASSIGNED', 'WAITING_APPROVAL', 'REPAIRING'])->count(),
            'platform_volume' => (float) FinancialLedger::where('entry_type', 'CUSTOMER_PAYMENT')->sum('amount'),
            'platform_commission' => (float) FinancialLedger::where('entry_type', 'PLATFORM_COMMISSION')->sum('amount'),
            'unsettled_tx_count' => AdminLedgerController::countUnsettledShops() + PaymentTransaction::where('status', 'PENDING')->count(),
        ];

        $recentPendingVendors = VendorProfile::with(['user', 'shops'])
            ->whereIn('verification_status', ['pending', 'under_review'])
            ->latest()
            ->take(4)
            ->get();

        $recentPendingRiders = DeliveryAgent::with('user')
            ->where('registration_status', 'PENDING')
            ->latest()
            ->take(4)
            ->get();

        $recentRequests = RepairRequest::with(['customer', 'requestedShop'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'kpis',
            'recentPendingVendors',
            'recentPendingRiders',
            'recentRequests'
        ));
    }
}
