<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\VendorProfile;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * Feature R12: Live Bidding Engine, Marketplace Filters & Quote Withdrawal
 * ============================================================================
 * Assigned Developer: Farhan Tanvir [22299179] | Sprint 2
 * MVC Layer: Controller
 * ============================================================================
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

            $ownedProfile = null;
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

            $vendorProfile = $ownedProfile
                ?? VendorProfile::with('shops')->where('shop_name', 'ProTech Solutions')->first()
                ?? VendorProfile::with('shops')->where('verification_status', 'approved')->first();

            $vendorShops = $vendorProfile
                ? $vendorProfile->shops->where('is_active', true)->values()
                : RepairShop::where('is_active', true)->where('is_public', true)->take(3)->get();
            $myShopIds = $vendorShops->pluck('id')->map(fn ($id) => (int) $id)->all();

            $search = trim((string) $request->query('q', ''));
            $deviceFilter = trim((string) $request->query('device_type', ''));
            $areaFilter = trim((string) $request->query('area', ''));
            $modeFilter = strtolower(trim((string) $request->query('mode', '')));
            $scopeFilter = strtolower(trim((string) $request->query('scope', 'all')));
            $sort = strtolower(trim((string) $request->query('sort', 'newest')));

            $query = RepairRequest::with([
                'customer',
                'bids' => fn ($bq) => $bq->where('status', '!=', 'WITHDRAWN')->with('shop'),
            ])
                ->withCount(['bids as active_bids_count' => fn ($bq) => $bq->where('status', '!=', 'WITHDRAWN')])
                ->whereIn('status', ['PENDING', 'BIDDING'])
                ->where(function ($q) {
                    $q->whereNull('source')->orWhere('source', '!=', 'DIRECT_SERVICE');
                });

            if ($search !== '') {
                $like = '%' . strtolower($search) . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(COALESCE(title, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(brand, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(model_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(problem, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(pickup_address, \'\')) LIKE ?', [$like]);
                });
            }

            if ($deviceFilter !== '') {
                $query->whereRaw('LOWER(COALESCE(device_type, \'\')) LIKE ?', ['%' . strtolower($deviceFilter) . '%']);
            }

            if ($areaFilter !== '') {
                $query->whereRaw('LOWER(COALESCE(pickup_address, \'\')) LIKE ?', ['%' . strtolower($areaFilter) . '%']);
            }

            if ($modeFilter === 'pickup') {
                $query->where('preferred_pickup', true);
            } elseif ($modeFilter === 'walk_in') {
                $query->where('preferred_pickup', false);
            }

            if ($scopeFilter === 'direct' && !empty($myShopIds)) {
                $query->whereIn('requested_shop_id', $myShopIds);
            } elseif ($scopeFilter === 'my_bids' && !empty($myShopIds)) {
                $query->whereHas('bids', fn ($bq) => $bq->whereIn('shop_id', $myShopIds)->where('status', '!=', 'WITHDRAWN'));
            } elseif ($scopeFilter === 'unbid' && !empty($myShopIds)) {
                $query->whereDoesntHave('bids', fn ($bq) => $bq->whereIn('shop_id', $myShopIds)->where('status', '!=', 'WITHDRAWN'));
            }

            if ($sort === 'fewest_bids') {
                $query->orderBy('active_bids_count', 'asc')->orderByDesc('id');
            } elseif ($sort === 'oldest') {
                $query->orderBy('created_at', 'asc');
            } else {
                $query->latest();
            }

            if ($request->boolean('poll')) {
                $count = (clone $query)->count();
                $latest = (clone $query)->latest('id')->first(['id', 'updated_at']);
                return response()->json([
                    'ok' => true,
                    'open_count' => $count,
                    'latest_id' => $latest?->id,
                    'server_time' => now()->format('h:i:s A'),
                ]);
            }

            $openRequests = $query->paginate(12)->withQueryString();

            return view('vendor.bidding-board', compact(
                'openRequests',
                'vendorShops',
                'search',
                'deviceFilter',
                'areaFilter',
                'modeFilter',
                'scopeFilter',
                'sort'
            ));
        }

        $bids = Bid::with('shop')
            ->where('repair_request_id', $requestId)
            ->where('status', '!=', 'WITHDRAWN')
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
     * Withdraw a vendor's active quote prior to customer acceptance.
     * Route: POST /vendor/bids/{bidId}/withdraw
     */
    public function withdrawBid(Request $request, $bidId)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $bid = Bid::with(['shop', 'repairRequest'])->findOrFail($bidId);
        $repairRequest = $bid->repairRequest;

        if ($user->role === 'vendor') {
            $vp = VendorProfile::where('user_id', $user->id)->first();
            if (!$vp || !$bid->shop || (int) $bid->shop->vendor_profile_id !== (int) $vp->id) {
                abort(403, 'You can only withdraw estimates submitted by your own workshop.');
            }
        } elseif ($user->role !== 'admin') {
            abort(403, 'Unauthorized quote withdrawal attempt.');
        }

        if (
            !$repairRequest
            || !in_array(strtoupper((string) $repairRequest->status), ['PENDING', 'BIDDING'], true)
            || (int) $repairRequest->selected_bid_id === (int) $bid->id
        ) {
            return back()->with('error', 'Accepted or closed estimates can no longer be withdrawn.');
        }

        $bid->update(['status' => 'WITHDRAWN']);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => 'Diagnostic estimate withdrawn.',
            ]);
        }

        return back()->with('success', 'Your diagnostic estimate on Ticket #' . $repairRequest->id . ' has been withdrawn.');
    }

    /**
     * Customer accepts a specific vendor bid (API endpoint synchronized with Web acceptBid).
     */
    public function acceptBid(Request $request, $bidId)
    {
        $bid = Bid::with('shop')->findOrFail($bidId);
        $repairRequest = RepairRequest::findOrFail($bid->repair_request_id);

        $subRequest = Request::create(
            "/requests/{$repairRequest->id}/accept-bid",
            'POST',
            ['bid_id' => $bid->id]
        );
        $subRequest->setUserResolver(fn () => $request->user());

        app(StatusTransitionController::class)->acceptBid($subRequest, $repairRequest->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Bid accepted! Repair request assigned to vendor.',
            'data' => $bid->fresh('shop'),
        ]);
    }
}
