<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\VendorProfile;
use Illuminate\Http\Request;

/**
 * Feature R15: Vendor Financial Analytics & Performance Dashboard
 * Assigned Developer: Farhan Tanvir [22299179] | Sprint 4
 * MVC Layer: Controller
 */
class VendorAnalyticsController extends Controller
{
    /**
     * Compute vendor performance metrics, accepted bid rates, and net earnings.
     */
    public function getPerformanceMetrics(Request $request, $vendorId)
    {
        $vendor = VendorProfile::findOrFail($vendorId);
        $totalBids = Bid::where('vendor_id', $vendorId)->count();
        $acceptedBids = Bid::where('vendor_id', $vendorId)->where('status', 'accepted')->count();

        $winRate = $totalBids > 0 ? round(($acceptedBids / $totalBids) * 100, 2) : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'shop_name' => $vendor->shop_name,
                'total_bids_submitted' => $totalBids,
                'accepted_jobs' => $acceptedBids,
                'bidding_win_rate_percentage' => $winRate,
                'average_rating' => $vendor->rating,
                'platform_commission_rate' => $vendor->commission_rate,
            ],
        ]);
    }
}
