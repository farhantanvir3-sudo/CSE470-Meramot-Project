<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\VendorProfile;
use Illuminate\Http\Request;

/**
 * Feature R12: Live Bidding Engine & Diagnostic Quote Generator
 * Assigned Developer: Farhan Tanvir [22299179] | Sprint 2
 * MVC Layer: Controller
 */
class BiddingEngineController extends Controller
{
    /**
     * List all bids for a specific repair request (JSON) or show live bidding board view.
     */
    public function index(Request $request, $requestId = null)
    {
        if (!$requestId && !$request->is('api/*')) {
            $user = $request->user();
            if (!$user) {
                return redirect()->route('login')->with(
                    'warning',
                    'Please sign in with your verified vendor account to access the Live Bidding Board.'
                );
            }

            if (in_array($user->role, ['delivery_agent', 'delivery'], true)) {
                return redirect('/delivery');
            }

            if ($user->role === 'customer') {
                return redirect()->route('vendor.apply')->with(
                    'warning',
                    'Only verified partner workshops can access the Live Bidding Board.'
                );
            }

            if ($user->role === 'vendor') {
                $ownedProfile = VendorProfile::with('shops')->where('user_id', $user->id)->first();
                if (!$ownedProfile) {
                    return redirect()->route('vendor.apply');
                }
                if ($ownedProfile->verification_status !== 'approved') {
                    return redirect()->route('vendor.onboarding')->with(
                        'warning',
                        "Your vendor application for '{$ownedProfile->shop_name}' must be approved by an admin before placing marketplace bids."
                    );
                }
                $primaryShop = $ownedProfile->shops->sortBy('id')->first();
                if ($primaryShop && !$primaryShop->setup_complete && $primaryShop->inspection_fee === null) {
                    return redirect()->route('vendor.setup-shop')->with(
                        'warning',
                        'Please complete your shop setup before placing marketplace bids.'
                    );
                }
            }

            $openRequests = RepairRequest::with(['customer', 'bids.shop'])
                ->whereIn('status', ['PENDING', 'BIDDING'])
                ->where(function ($q) {
                    $q->whereNull('source')->orWhere('source', '!=', 'DIRECT_SERVICE');
                })
                ->latest()
                ->take(10)
                ->get();

            $vendorProfile = $request->user()
                ? VendorProfile::with('shops')->where('user_id', $request->user()->id)->first()
                : null;
            if (!$vendorProfile) {
                $vendorProfile = VendorProfile::with('shops')->where('shop_name', 'ProTech Solutions')->first()
                    ?? VendorProfile::with('shops')->where('verification_status', 'approved')->first();
            }
            $vendorShops = $vendorProfile
                ? $vendorProfile->shops->where('is_active', true)->values()
                : RepairShop::where('is_active', true)->where('is_public', true)->take(3)->get();

            return view('vendor.bidding-board', compact('openRequests', 'vendorShops'));
        }

        $bids = Bid::with('shop')
            ->where('repair_request_id', $requestId)
            ->orderBy('proposed_cost', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $bids,
        ]);
    }

    /**
     * Submit a new diagnostic quote/bid from a verified vendor (API + Web compatible).
     */
    public function store(Request $request, $requestId)
    {
        return app(VendorBranchController::class)->submitBid($request, $requestId);
    }

    /**
     * Customer accepts a specific vendor bid (API endpoint synchronized with Web acceptBid).
     */
    public function acceptBid($bidId)
    {
        $bid = Bid::with('shop')->findOrFail($bidId);
        $repairRequest = RepairRequest::findOrFail($bid->repair_request_id);

        app(StatusTransitionController::class)->acceptBid(
            new Request(['bid_id' => $bid->id]),
            $repairRequest->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Bid accepted! Repair request assigned to vendor.',
            'data' => $bid->fresh('shop'),
        ]);
    }
}
