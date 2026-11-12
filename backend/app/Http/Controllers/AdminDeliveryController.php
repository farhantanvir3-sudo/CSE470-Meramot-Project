<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\User;
use App\Services\GmailApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * FEATURE R1: Delivery Agent Onboarding & Credential Verification Controller
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Commits by Nafiul:
 *   - c6c0781: Delivery agent application form, routes, and approve/reject
 *   - d71bb8f: Show license number on admin verify page
 *   - fdf5e6f: Add rejection_reason validation & persistence on admin reject
 * Merged with Original Meramot Delivery Partner System:
 *   - Farhan Tanvir [Student ID: 22299179]
 *   - 6 Operations KPI StatCards (`pendingRegistrations`, `activeApprovedPartners`,
 *     `completedDeliveriesTotal`, `partnersWithCompletedDeliveries`, `totalPartners`,
 *     `rejectedPartners`)
 *   - Document uploads (`profile_picture`, `nid_document`, `education_document`, `cv_document`)
 *   - Auto-generated rider credentials on approval + partner block/suspend action
 * Views Rendered (relocated to root `frontend/views/`):
 *   - frontend/views/admin/verify-agent.blade.php
 *   - frontend/views/delivery/register.blade.php
 * ============================================================================
 */
class AdminDeliveryController extends Controller
{
    /* ------------------------------------------------------------------------
     * BLOCK 1: Pending Delivery Agent Verification Desk & Partner Operations
     * Original Author: Md. Nafiul Islam [22299035] (Commit c6c0781)
     * Merged with Original Meramot DeliveryAdminDashboard Stats & Directory
     * Route: GET /admin/delivery-agents/pending
     * ------------------------------------------------------------------------ */
    public function index()
    {
        // Nafiul's core query: pending delivery agents awaiting verification
        $pendingAgents = DeliveryAgent::with('user')
            ->where('registration_status', 'PENDING')
            ->orderBy('created_at', 'desc')
            ->get();

        // All delivery partner profiles with completed delivery counts
        $allPartners = DeliveryAgent::with('user')
            ->withCount([
                'deliveryJobs as completed_deliveries_count' => function ($q) {
                    $q->whereIn('status', ['DELIVERED', 'delivered', 'COMPLETED', 'completed']);
                },
            ])
            ->orderBy('updated_at', 'desc')
            ->get();

        $completedDeliveriesTotal = DeliveryJob::whereIn('status', ['DELIVERED', 'delivered', 'COMPLETED', 'completed'])->count();
        $partnersWithCompletedDeliveries = $allPartners->where('completed_deliveries_count', '>', 0)->count();

        $stats = [
            'pendingRegistrations' => $pendingAgents->count(),
            'activeApprovedPartners' => $allPartners->where('registration_status', 'APPROVED')->where('is_active', true)->count(),
            'completedDeliveriesTotal' => $completedDeliveriesTotal,
            'partnersWithCompletedDeliveries' => $partnersWithCompletedDeliveries,
            'totalPartners' => $allPartners->count(),
            'rejectedPartners' => $allPartners->where('registration_status', 'REJECTED')->count(),
        ];

        return view('admin.verify-agent', compact('pendingAgents', 'allPartners', 'stats'));
    }

    /* ------------------------------------------------------------------------
     * BLOCK 2: Delivery Agent Application Form View
     * Original Author: Md. Nafiul Islam [22299035] (Commit c6c0781)
     * View Relocated To: frontend/views/delivery/register.blade.php
     * Route: GET /delivery-agent/register
     * ------------------------------------------------------------------------ */
    public function create(Request $request)
    {
        $userId = $request->user()?->id;
        $existingApplication = $userId
            ? DeliveryAgent::where('user_id', $userId)->first()
            : null;

        return view('delivery.register', compact('existingApplication'));
    }

    /* ------------------------------------------------------------------------
     * BLOCK 3: Store New Delivery Agent Application
     * Original Author: Md. Nafiul Islam [22299035] (Commit c6c0781)
     * Merged with Original Meramot Partner Onboarding (Personal Details +
     * Document Uploads for Profile Picture, NID, Education & CV).
     * Route: POST /delivery-agent/register
     * ------------------------------------------------------------------------ */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Nafiul's core R1 fields (Commit c6c0781)
            'nid_number' => 'required|string|max:30',
            'vehicle_type' => 'required|in:motorcycle,bicycle,scooter,van,car',
            'license_number' => 'required|string|max:50',
            // Original Meramot Partner Onboarding optional personal & document fields
            'name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:30',
            'profile_picture' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
            'nid_document' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
            'education_document' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
            'cv_document' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
            'profile_picture_url' => 'nullable|string|max:500',
            'nid_document_url' => 'nullable|string|max:500',
            'education_document_url' => 'nullable|string|max:500',
            'cv_document_url' => 'nullable|string|max:500',
        ]);

        // Handle optional file uploads or URL inputs
        $profilePicUrl = $validated['profile_picture_url'] ?? null;
        if ($request->hasFile('profile_picture')) {
            $profilePicUrl = '/storage/' . $request->file('profile_picture')->store('delivery-docs/profile', 'public');
        }

        $nidDocUrl = $validated['nid_document_url'] ?? null;
        if ($request->hasFile('nid_document')) {
            $nidDocUrl = '/storage/' . $request->file('nid_document')->store('delivery-docs/nid', 'public');
        }

        $eduDocUrl = $validated['education_document_url'] ?? null;
        if ($request->hasFile('education_document')) {
            $eduDocUrl = '/storage/' . $request->file('education_document')->store('delivery-docs/education', 'public');
        }

        $cvDocUrl = $validated['cv_document_url'] ?? null;
        if ($request->hasFile('cv_document')) {
            $cvDocUrl = '/storage/' . $request->file('cv_document')->store('delivery-docs/cv', 'public');
        }

        $agent = DB::transaction(function () use ($request, $validated, $profilePicUrl, $nidDocUrl, $eduDocUrl, $cvDocUrl) {
            $user = $request->user();

            // If guest applicant supplied email/name, find or create a pending partner User
            if (!$user && !empty($validated['email'])) {
                $cleanEmail = strtolower(trim($validated['email']));
                $user = User::firstOrCreate(
                    ['email' => $cleanEmail],
                    [
                        'name' => trim($validated['name'] ?? 'Delivery Applicant'),
                        'username' => 'pending_' . strtolower(Str::random(8)),
                        'phone' => trim($validated['phone'] ?? '+8801700000000'),
                        'password' => Hash::make(Str::random(16)),
                        'role' => 'delivery_agent',
                        'status' => 'suspended',
                        'is_active' => false,
                        'avatar_url' => $profilePicUrl,
                    ]
                );
            }

            $userId = $user?->id
                ?? User::where('role', 'delivery_agent')->value('id')
                ?? User::value('id')
                ?? 1;

            $vehicleType = $validated['vehicle_type'] === 'car' ? 'van' : $validated['vehicle_type'];

            return DeliveryAgent::updateOrCreate(
                ['user_id' => $userId],
                [
                    'nid_number' => $validated['nid_number'],
                    'vehicle_type' => $vehicleType,
                    'license_number' => $validated['license_number'],
                    'profile_picture_url' => $profilePicUrl,
                    'nid_document_url' => $nidDocUrl,
                    'education_document_url' => $eduDocUrl,
                    'cv_document_url' => $cvDocUrl,
                    'registration_status' => 'PENDING',
                    'status' => 'OFFLINE',
                    'is_active' => false,
                    'rejection_reason' => null,
                ]
            );
        });

        $agent->loadMissing('user');
        if ($agent->user && !empty($agent->user->email)) {
            app(GmailApiService::class)->sendDeliveryRegistrationAcknowledgement(
                $agent->user->email,
                $agent->user->name ?? 'Delivery Partner Applicant'
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Delivery agent registration submitted for admin approval.',
                'data' => $agent,
            ], 201);
        }

        return redirect()
            ->back()
            ->with('success', 'Delivery partner application submitted! Confirmation sent from meramotbd.official@gmail.com.');
    }

    /* ------------------------------------------------------------------------
     * BLOCK 4: Admin Approve Delivery Agent Application
     * Original Author: Md. Nafiul Islam [22299035] (Commit c6c0781)
     * Merged with Original Meramot `approveDeliveryPartner`: activates User,
     * sets status to AVAILABLE, and generates partner credentials.
     * Route: POST /admin/delivery-agents/{id}/approve
     * ------------------------------------------------------------------------ */
    public function approve($id)
    {
        $agent = DeliveryAgent::with('user')->findOrFail($id);

        $generatedUsername = $agent->user?->username;
        $isNewPendingGuest = !$generatedUsername || str_starts_with($generatedUsername, 'pending_');
        if ($isNewPendingGuest) {
            $generatedUsername = 'rider_' . $agent->id . '_' . strtolower(Str::random(4));
        }

        // Keep `password123` for seeded demo accounts; generate a secure rider password for guest applicants
        $seededEmails = [
            'rider.demo@meramot.test',
            'ctg.rider@meramot.test',
            'kamal.rider@meramot.test',
            'tarek.scooter@meramot.test',
            'sohag.van@meramot.test',
        ];
        $isSeededRider = $agent->user && in_array($agent->user->email, $seededEmails, true);
        $plainPassword = $isSeededRider
            ? 'password123'
            : ('Meramot@' . strtoupper(Str::random(4)) . random_int(10, 99));

        $previousStatus = $agent->registration_status;

        $agent->update([
            'registration_status' => 'APPROVED',
            'status' => 'AVAILABLE',
            'is_active' => true,
            'rejection_reason' => null,
        ]);

        if ($agent->user) {
            $updateData = [
                'username' => $generatedUsername,
                'role' => 'delivery_agent',
                'status' => 'active',
                'is_active' => true,
            ];
            if (!$isSeededRider) {
                $updateData['password'] = Hash::make($plainPassword);
            }
            $agent->user->update($updateData);

            if (!empty($agent->user->email)) {
                app(GmailApiService::class)->sendDeliveryApprovalCredentials(
                    $agent->user->email,
                    $agent->user->name ?? $generatedUsername,
                    $generatedUsername,
                    $plainPassword
                );
            }
        }

        AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => 'delivery_agent_approved',
            'target_resource' => "delivery_agents:{$agent->id}",
            'payload_before' => ['registration_status' => $previousStatus],
            'payload_after' => ['registration_status' => 'APPROVED', 'username' => $generatedUsername],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        Log::info("Delivery partner #{$agent->id} approved by Admin.", [
            'agent_id' => $agent->id,
            'username' => $generatedUsername,
        ]);

        return redirect()->back()->with(
            'success',
            "Delivery agent #{$agent->id} (" . ($agent->user->name ?? $generatedUsername) . ") approved! Login credentials emailed from meramotbd.official@gmail.com."
        );
    }

    /* ------------------------------------------------------------------------
     * BLOCK 5: Admin Reject Delivery Agent Application with Rejection Reason
     * Original Author: Md. Nafiul Islam [22299035] (Commits c6c0781 & fdf5e6f)
     * Validates `rejection_reason` and saves it alongside `REJECTED` status.
     * Route: POST /admin/delivery-agents/{id}/reject
     * ------------------------------------------------------------------------ */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $agent = DeliveryAgent::with('user')->findOrFail($id);
        $previousStatus = $agent->registration_status;

        $agent->update([
            'registration_status' => 'REJECTED',
            'rejection_reason' => $validated['rejection_reason'],
            'status' => 'SUSPENDED',
            'is_active' => false,
        ]);

        if ($agent->user) {
            $agent->user->update([
                'status' => 'suspended',
                'is_active' => false,
            ]);

            if (!empty($agent->user->email)) {
                app(GmailApiService::class)->sendDeliveryRejectionNotice(
                    $agent->user->email,
                    $agent->user->name ?? 'Delivery Applicant',
                    $validated['rejection_reason']
                );
            }
        }

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'delivery_agent_rejected',
            'target_resource' => "delivery_agents:{$agent->id}",
            'payload_before' => ['registration_status' => $previousStatus],
            'payload_after' => ['registration_status' => 'REJECTED', 'rejection_reason' => $validated['rejection_reason']],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return redirect()->back()->with('warning', 'Delivery agent application rejected and reason recorded.');
    }

    /* ------------------------------------------------------------------------
     * BLOCK 6: Admin Block / Suspend Active Delivery Partner
     * Ported from Original Meramot `blockDeliveryPartner`
     * Route: POST /admin/delivery-agents/{id}/block
     * ------------------------------------------------------------------------ */
    public function block($id)
    {
        $agent = DeliveryAgent::with('user')->findOrFail($id);
        $agent->update([
            'status' => 'SUSPENDED',
            'is_active' => false,
        ]);

        if ($agent->user) {
            $agent->user->update([
                'status' => 'suspended',
                'is_active' => false,
            ]);
        }

        return redirect()->back()->with('warning', "Delivery partner #{$agent->id} suspended.");
    }
}
