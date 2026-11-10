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
            $openRequests = RepairRequest::with(['customer', 'bids.shop'])
                ->where('status', 'BIDDING')
                ->latest()
                ->take(10)
                ->get();

            $vendorProfile = $request->user()
                ? VendorProfile::with('shops')->where('user_id', $request->user()->id)->first()
                : null;
            if (!$vendorProfile) {
                $vendorProfile = VendorProfile::with('shops')->where('shop_name', 'ProTech Solutions')->first()
                    ?? VendorProfile::with('shops')->first();
            }
            $vendorShops = $vendorProfile ? $vendorProfile->shops : RepairShop::take(3)->get();

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
     * Customer accepts a specific vendor bid.
     */
    public function acceptBid($bidId)
    {
        $bid = Bid::findOrFail($bidId);
        $bid->update(['status' => 'ACCEPTED']);

        $repairRequest = RepairRequest::findOrFail($bid->repair_request_id);
        $repairRequest->update([
            'status' => 'ASSIGNED',
            'selected_bid_id' => $bid->id,
            'quoted_final_amount' => $bid->proposed_cost,
        ]);

        Bid::where('repair_request_id', $bid->repair_request_id)
            ->where('id', '!=', $bid->id)
            ->update(['status' => 'DECLINED']);

        return response()->json([
            'status' => 'success',
            'message' => 'Bid accepted! Repair request assigned to vendor.',
            'data' => $bid,
        ]);
    }
}
