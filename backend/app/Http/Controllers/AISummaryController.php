<?php

namespace App\Http\Controllers;

use App\Models\AISummaryLog;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\VendorProfile;
use App\Services\AISummaryService;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * Feature R13: FastAPI GROQ/Gemini AI Request Summary & Hardware Triage Client
 * ============================================================================
 * Assigned Developer: Farhan Tanvir [22299179] | Sprint 2
 * MVC Layer: Controller
 * ============================================================================
 */
class AISummaryController extends Controller
{
    public function __construct(private readonly AISummaryService $summaryService)
    {
    }

    /**
     * Render the Vendor Request Triage & Quote Workspace (`GET /vendor/request-details/{id}`).
     */
    public function showRequestDetails(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login')->with('warning', 'Please sign in to view diagnostic request triage.');
        }

        if (in_array($user->role, ['delivery_agent', 'delivery'], true)) {
            return redirect('/delivery');
        }

        if ($user->role === 'customer') {
            return redirect()->route('requests.track', $id);
        }

        $vendorProfile = null;
        if ($user->role === 'vendor') {
            $vendorProfile = VendorProfile::with('shops')->where('user_id', $user->id)->first();
            if (!$vendorProfile) {
                return redirect()->route('vendor.apply');
            }
            if ($vendorProfile->verification_status !== 'approved') {
                return redirect()->route('vendor.onboarding')->with(
                    'warning',
                    'Your workshop must be approved before viewing full marketplace triage leads.'
                );
            }
        }

        $repairRequest = RepairRequest::with([
            'customer',
            'bids' => fn ($bq) => $bq->where('status', '!=', 'WITHDRAWN')->with('shop'),
            'requestedShop',
        ])->find($id);

        if (!$repairRequest) {
            $repairRequest = RepairRequest::with([
                'customer',
                'bids' => fn ($bq) => $bq->where('status', '!=', 'WITHDRAWN')->with('shop'),
                'requestedShop',
            ])->latest()->first();
        }

        $triageData = null;
        $summaryLogs = collect();

        if ($repairRequest) {
            if (empty($repairRequest->ai_summary) || empty($repairRequest->ai_triage_json)) {
                $triageData = $this->summaryService->summarize(
                    $repairRequest,
                    $user->id,
                    !app()->environment('testing')
                );
                $repairRequest->refresh();
            }

            $triageData = is_array($repairRequest->ai_triage_json)
                ? $repairRequest->ai_triage_json
                : $triageData;

            $summaryLogs = AISummaryLog::where('repair_request_id', $repairRequest->id)
                ->latest()
                ->take(5)
                ->get();
        }

        if (!$vendorProfile) {
            $vendorProfile = VendorProfile::with('shops')->where('verification_status', 'approved')->first();
        }

        $vendorShops = $vendorProfile
            ? $vendorProfile->shops->where('is_active', true)->values()
            : RepairShop::where('is_active', true)->where('is_public', true)->take(3)->get();

        return view('vendor.request-details', compact(
            'repairRequest',
            'triageData',
            'summaryLogs',
            'vendorShops'
        ));
    }

    /**
     * Re-run AI Hardware Fault Triage on demand (`POST /vendor/request-details/{id}/regenerate`).
     */
    public function regenerateSummary(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['vendor', 'admin'], true)) {
            abort(403, 'Only approved workshops or administrators can trigger AI fault triage.');
        }

        $repairRequest = RepairRequest::findOrFail($id);
        $result = $this->summaryService->summarize($repairRequest, $user->id, true);

        return redirect("/vendor/request-details/{$repairRequest->id}")
            ->with('success', 'AI fault triage refreshed via ' . ($result['provider'] ?? 'diagnostic engine') . ' (' . ($result['latency_ms'] ?? 0) . ' ms).');
    }

    /**
     * JSON API Endpoint (`POST /api/requests/{requestId}/ai-summary`).
     */
    public function generateSummary(Request $request, $requestId)
    {
        $repairRequest = RepairRequest::findOrFail($requestId);
        $result = $this->summaryService->summarize(
            $repairRequest,
            $request->user()?->id,
            true
        );

        return response()->json([
            'status' => 'success',
            'message' => 'AI Diagnostic summary generated.',
            'data' => [
                'summary' => $result['summary'],
                'provider' => $result['provider'],
                'model_name' => $result['model_name'],
                'estimated_component_faults' => $result['suspected_faults'] ?? [],
                'suggested_parts' => $result['suggested_parts'] ?? [],
                'risk_level' => $result['risk_level'] ?? 'MEDIUM',
                'estimated_bench_hours' => $result['estimated_bench_time_hours'] ?? 2.0,
                'estimated_cost_min' => $result['estimated_cost_min'] ?? null,
                'estimated_cost_max' => $result['estimated_cost_max'] ?? null,
            ],
        ]);
    }
}
