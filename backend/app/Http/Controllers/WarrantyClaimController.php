<?php

namespace App\Http\Controllers;

use App\Models\IssuedWarranty;
use App\Models\SparePartWarranty;
use App\Models\WarrantyClaim;
use App\Services\WarrantyLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class WarrantyClaimController extends Controller
{
    /**
     * Customer submits a warranty claim (repeatable while warranty window is active).
     * Route: POST /warranties/{warranty}/claims
     */
    public function store(Request $request, int $warrantyId)
    {
        $user = $request->user();
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Authentication required.'], 401)
                : redirect()->guest(route('login'));
        }

        if (!in_array($user->role, ['customer', 'vendor'], true)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Only order owners may submit warranty claims.'], 403)
                : back()->with('error', 'Only the order owner may submit warranty claims.');
        }

        $warranty = IssuedWarranty::with(['shop.vendorProfile.user', 'repairRequest'])->findOrFail($warrantyId);

        $maxQty = max(1, (int) $warranty->quantity);
        $validated = $request->validate([
            'preferred_remedy' => 'nullable|string|in:AUTO,REPLACEMENT,FREE_REPAIR,REFUND',
            'claimed_quantity' => "nullable|integer|min:1|max:{$maxQty}",
            'explanation' => 'required|string|min:15|max:3000',
            'evidence_images' => 'nullable|array|max:5',
            'evidence_images.*' => 'file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif|max:10240',
        ], [
            'explanation.min' => 'Please describe the hardware/part issue in at least 15 characters so Admin can assess your claim.',
            'evidence_images.max' => 'You can upload up to 5 supporting images per warranty claim.',
        ]);

        $uploadedFiles = $request->hasFile('evidence_images')
            ? (array) $request->file('evidence_images')
            : [];

        $claim = app(WarrantyLifecycleService::class)->submitClaim(
            $warranty,
            $user,
            $validated,
            $uploadedFiles
        );

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => "Warranty Claim #{$claim->claim_number} submitted for admin assessment.",
                'claim' => $claim,
            ], 201);
        }

        return redirect()
            ->to('/requests/' . $warranty->repair_request_id . '/track#warranty-section')
            ->with('success', "Warranty Claim #{$claim->claim_number} (Ticket #{$claim->id}) submitted! Platform Admin will review your explanation & photos shortly.");
    }

    /**
     * Admin Warranty Registry & Claims Arbitration Desk.
     * Route: GET /admin/warranties
     */
    public function adminIndex(Request $request)
    {
        $statusFilter = strtoupper(trim((string) $request->query('status', 'ALL')));
        $search = trim((string) $request->query('q', ''));

        $claimsQuery = WarrantyClaim::with([
            'issuedWarranty',
            'repairRequest',
            'customer',
            'shop',
            'sparePartListing',
            'reviewer',
            'followupRepairRequest',
        ])->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")
          ->orderByDesc('misuse_risk_score')
          ->orderByDesc('id');

        if ($statusFilter === 'PENDING') {
            $claimsQuery->where('status', WarrantyClaim::STATUS_PENDING);
        } elseif ($statusFilter === 'HIGH_RISK') {
            $claimsQuery->where('misuse_risk_score', '>=', 25);
        } elseif ($statusFilter === 'APPROVED') {
            $claimsQuery->whereIn('status', [
                WarrantyClaim::STATUS_APPROVED_REFUND,
                WarrantyClaim::STATUS_APPROVED_REPLACEMENT,
                WarrantyClaim::STATUS_APPROVED_FREE_REPAIR,
            ]);
        } elseif ($statusFilter === 'REJECTED') {
            $claimsQuery->where('status', WarrantyClaim::STATUS_REJECTED);
        }

        if ($search !== '') {
            $likeOp = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $claimsQuery->where(function ($q) use ($term, $likeOp, $search) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search)
                      ->orWhere('repair_request_id', (int) $search);
                }
                $q->orWhere('customer_explanation', $likeOp, $term)
                  ->orWhereHas('customer', fn ($u) => $u->where('name', $likeOp, $term)->orWhere('email', $likeOp, $term))
                  ->orWhereHas('issuedWarranty', fn ($iw) => $iw->where('item_title', $likeOp, $term));
            });
        }

        $claims = $claimsQuery->paginate(15)->withQueryString();

        // Compute per-customer order/claim context for anti-misuse inspection
        $customerIds = $claims->getCollection()->pluck('customer_id')->unique()->filter()->values();
        $customerStats = [];
        if ($customerIds->isNotEmpty()) {
            $claimCounts = WarrantyClaim::whereIn('customer_id', $customerIds)
                ->selectRaw('customer_id, COUNT(*) as total_claims, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected_claims', [WarrantyClaim::STATUS_REJECTED])
                ->groupBy('customer_id')
                ->get()
                ->keyBy('customer_id');

            $orderCounts = \App\Models\RepairRequest::whereIn('customer_id', $customerIds)
                ->where('status', \App\Models\RepairRequest::STATUS_COMPLETED)
                ->selectRaw('customer_id, COUNT(*) as completed_orders')
                ->groupBy('customer_id')
                ->get()
                ->keyBy('customer_id');

            foreach ($customerIds as $cid) {
                $cc = $claimCounts->get($cid);
                $oc = $orderCounts->get($cid);
                $customerStats[(int) $cid] = [
                    'total_claims' => (int) ($cc->total_claims ?? 0),
                    'rejected_claims' => (int) ($cc->rejected_claims ?? 0),
                    'completed_orders' => (int) ($oc->completed_orders ?? 0),
                ];
            }
        }

        $kpis = [
            'warrantied_listings' => Schema::hasTable('spare_part_warranties') ? SparePartWarranty::count() : 0,
            'active_warranties' => Schema::hasTable('issued_warranties')
                ? IssuedWarranty::where('status', IssuedWarranty::STATUS_ACTIVE)->where('expires_at', '>=', now())->count()
                : 0,
            'pending_claims' => WarrantyClaim::where('status', WarrantyClaim::STATUS_PENDING)->count(),
            'high_risk_pending' => WarrantyClaim::where('status', WarrantyClaim::STATUS_PENDING)->where('misuse_risk_score', '>=', 25)->count(),
            'approved_claims' => WarrantyClaim::whereIn('status', [
                WarrantyClaim::STATUS_APPROVED_REFUND,
                WarrantyClaim::STATUS_APPROVED_REPLACEMENT,
                WarrantyClaim::STATUS_APPROVED_FREE_REPAIR,
            ])->count(),
            'rejected_claims' => WarrantyClaim::where('status', WarrantyClaim::STATUS_REJECTED)->count(),
        ];

        $registryPreview = Schema::hasTable('spare_part_warranties')
            ? SparePartWarranty::with(['listing', 'shop'])->orderByDesc('updated_at')->limit(12)->get()
            : collect();

        return view('admin.warranties', compact(
            'claims',
            'statusFilter',
            'search',
            'kpis',
            'customerStats',
            'registryPreview'
        ));
    }

    /**
     * Admin reviews & resolves a warranty claim (REFUND, REPLACEMENT, FREE_REPAIR, or REJECT with reply + images).
     * Route: POST /admin/warranties/{claim}/assess
     */
    public function assess(Request $request, int $claimId)
    {
        $claim = WarrantyClaim::with('issuedWarranty')->findOrFail($claimId);
        $decision = strtoupper(trim((string) $request->input('decision', '')));

        $rules = [
            'decision' => 'required|string|in:REFUND,REPLACEMENT,FREE_REPAIR,REJECT',
            'admin_reply' => $decision === 'REJECT'
                ? 'required|string|min:20|max:3000'
                : 'required|string|min:5|max:3000',
            'refund_amount' => 'nullable|numeric|min:1|max:500000',
            'admin_images' => $decision === 'REJECT'
                ? 'required|array|min:1|max:5'
                : 'nullable|array|max:5',
            'admin_images.*' => 'file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif|max:10240',
        ];

        $messages = [
            'admin_reply.min' => $decision === 'REJECT'
                ? 'Rejection requires a thorough explanation (minimum 20 characters) detailing why the warranty claim was declined.'
                : 'Please provide an assessment note for the customer.',
            'admin_images.required' => 'Rejection requires uploading at least 1 supporting inspection/proof image.',
            'admin_images.min' => 'Rejection requires uploading at least 1 supporting inspection/proof image.',
        ];

        $validated = $request->validate($rules, $messages);
        $adminFiles = $request->hasFile('admin_images') ? (array) $request->file('admin_images') : [];

        try {
            $updated = app(WarrantyLifecycleService::class)->assessClaim(
                $claim,
                $request->user(),
                $validated,
                $adminFiles
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => "Warranty Claim #{$updated->id} assessed: {$updated->statusBadgeLabel()}",
                'claim' => $updated,
            ]);
        }

        return back()->with('success', "Warranty Claim #{$updated->id} assessed: {$updated->statusBadgeLabel()}.");
    }
}
