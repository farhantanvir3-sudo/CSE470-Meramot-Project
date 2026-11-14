<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\User;
use App\Services\GmailApiService;
use App\Services\MediaUploadService;
use App\Support\InputSanitizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ============================================================================
 * FEATURE R1: Delivery Agent Onboarding & Credential Verification Controller
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Commits by Nafiul:
 *   - c6c0781: Delivery agent application form, routes, and approve/reject
 *   - d71bb8f: Show license number on admin verify page
 *   - fdf5e6f: Add rejection_reason validation & persistence on admin reject
 * Renewed Onboarding, OTP Status Check, Unlimited Edits & `@meramotbd.com`
 * One-Time Company Credential Architecture:
 *   - Farhan Tanvir [Student ID: 22299179]
 * Views Rendered (`frontend/views/`):
 *   - frontend/views/admin/verify-agent.blade.php
 *   - frontend/views/delivery/register.blade.php
 *   - frontend/views/delivery/status.blade.php
 *   - frontend/views/delivery/edit.blade.php
 * ============================================================================
 */
class AdminDeliveryController extends Controller
{
    /**
     * Encode an uploaded image/PDF as a base64 data URI so Admin document verification
     * works reliably across ephemeral cloud containers (Render) as well as localhost.
     */
    private function encodeUploadedDocument(Request $request, string $inputKey, ?string $fallback = null): ?string
    {
        return MediaUploadService::fromRequest($request, $inputKey, $fallback);
    }

    /**
     * Derive unique `<localpart>@meramotbd.com` company email from applicant's personal email.
     */
    private function generateUniqueCompanyEmail(string $personalEmail, ?int $ignoreAgentId = null, ?int $ignoreUserId = null): string
    {
        $rawLocal = strtolower(explode('@', trim($personalEmail))[0] ?? '');
        $sanitized = preg_replace('/[^a-z0-9._-]/', '', $rawLocal) ?: 'rider';
        $sanitized = trim($sanitized, '._-') ?: 'rider';

        $candidate = $sanitized . '@meramotbd.com';
        $counter = 2;

        while (
            DeliveryAgent::whereRaw('LOWER(company_email) = ?', [$candidate])
                ->when($ignoreAgentId, fn ($q) => $q->where('id', '!=', $ignoreAgentId))
                ->exists()
            || User::whereRaw('LOWER(email) = ?', [$candidate])
                ->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))
                ->exists()
        ) {
            $candidate = $sanitized . $counter . '@meramotbd.com';
            $counter++;
        }

        return $candidate;
    }

    /**
     * Generate a high-entropy, unchangeable 16-character Meramot Rider company password.
     */
    private function generateSecureCompanyPassword(): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $symbols = '@#$!%&*';

        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        $pool = $upper . $lower . $digits . $symbols;
        for ($i = 0; $i < 5; $i++) {
            $chars[] = $pool[random_int(0, strlen($pool) - 1)];
        }
        shuffle($chars);

        return 'MRM#' . implode('', $chars);
    }

    /* ------------------------------------------------------------------------
     * BLOCK 1: Pending Delivery Agent Verification Desk & Partner Operations
     * Original Author: Md. Nafiul Islam [22299035] (Commit c6c0781)
     * Route: GET /admin/delivery-agents/pending
     * ------------------------------------------------------------------------ */
    public function index()
    {
        $pendingAgents = DeliveryAgent::with('user')
            ->where('registration_status', 'PENDING')
            ->orderBy('updated_at', 'desc')
            ->get();

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
     * BLOCK 2: Blank Delivery Agent Application Form View
     * Original Author: Md. Nafiul Islam [22299035] (Commit c6c0781)
     * Note: Never pre-fills demo or logged-in rider data!
     * Route: GET /delivery-agent/register
     * ------------------------------------------------------------------------ */
    public function create()
    {
        return view('delivery.register');
    }

    /* ------------------------------------------------------------------------
     * BLOCK 3: Store New Delivery Agent Application
     * Original Author: Md. Nafiul Islam [22299035] (Commit c6c0781)
     * Route: POST /delivery-agent/register
     * ------------------------------------------------------------------------ */
    public function store(Request $request)
    {
        $isJsonApi = $request->expectsJson();

        $rules = [
            'nid_number' => 'required|string|max:30',
            'vehicle_type' => 'required|in:motorcycle,bicycle,scooter,van,car',
            'license_number' => 'required|string|max:50',
            'name' => $isJsonApi ? 'nullable|string|max:120' : 'required|string|max:120',
            'phone' => $isJsonApi ? 'nullable|string|max:30' : 'required|string|max:30',
            'personal_email' => $isJsonApi ? 'nullable|email|max:150' : 'required_without:email|nullable|email|max:150',
            'email' => 'nullable|email|max:150',
            'profile_picture' => 'nullable|file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/avif|max:15360',
            'nid_document' => 'nullable|file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/avif,application/pdf|max:15360',
            'license_document' => 'nullable|file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/avif,application/pdf|max:15360',
            'education_document' => 'nullable|file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/avif,application/pdf|max:15360',
            'cv_document' => 'nullable|file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/avif,application/pdf|max:15360',
            'profile_picture_url' => 'nullable|string',
            'nid_document_url' => 'nullable|string',
            'license_document_url' => 'nullable|string',
            'education_document_url' => 'nullable|string',
            'cv_document_url' => 'nullable|string',
        ];

        $validated = $request->validate($rules);

        $rawPersonalEmail = $validated['personal_email']
            ?? $validated['email']
            ?? ($isJsonApi ? $request->user()?->email : null);

        $personalEmail = $rawPersonalEmail ? strtolower(trim($rawPersonalEmail)) : null;

        if (!$isJsonApi) {
            if (!$personalEmail) {
                throw ValidationException::withMessages([
                    'personal_email' => 'Please enter your personal email address.',
                ]);
            }

            if (str_ends_with($personalEmail, '@meramotbd.com')) {
                throw ValidationException::withMessages([
                    'personal_email' => 'Please enter your real personal email (e.g. Gmail, Yahoo, Outlook), not a @meramotbd.com company email.',
                ]);
            }

            $existingByEmail = DeliveryAgent::whereRaw('LOWER(personal_email) = ?', [$personalEmail])->first();
            if ($existingByEmail) {
                throw ValidationException::withMessages([
                    'personal_email' => 'An application with this personal email already exists. Use "Check Application Status" to view or edit your application.',
                ]);
            }
        }

        $profilePicUrl = $this->encodeUploadedDocument($request, 'profile_picture', $validated['profile_picture_url'] ?? null);
        $nidDocUrl = $this->encodeUploadedDocument($request, 'nid_document', $validated['nid_document_url'] ?? null);
        $licenseDocUrl = $this->encodeUploadedDocument($request, 'license_document', $validated['license_document_url'] ?? null);
        $eduDocUrl = $this->encodeUploadedDocument($request, 'education_document', $validated['education_document_url'] ?? null);
        $cvDocUrl = $this->encodeUploadedDocument($request, 'cv_document', $validated['cv_document_url'] ?? null);

        $applicantName = trim((string) ($validated['name'] ?? ($isJsonApi ? ($request->user()?->name ?? 'Delivery Applicant') : 'Delivery Applicant')));
        $applicantPhone = InputSanitizer::sanitizePhone($validated['phone'] ?? ($isJsonApi ? ($request->user()?->phone ?? null) : null));
        if (!$isJsonApi && !InputSanitizer::isValidBdPhone($applicantPhone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid Bangladeshi mobile number (e.g. 01712345678).',
            ]);
        }
        if ($isJsonApi && $applicantPhone !== null && !InputSanitizer::isValidBdPhone($applicantPhone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid Bangladeshi mobile number (e.g. 01712345678).',
            ]);
        }

        $cleanNid = InputSanitizer::sanitizeDigits($validated['nid_number']);
        if (!InputSanitizer::isValidNid($cleanNid)) {
            throw ValidationException::withMessages([
                'nid_number' => 'National ID must be 10, 13, or 17 digits (numbers only).',
            ]);
        }

        $vehicleType = $validated['vehicle_type'] === 'car' ? 'van' : $validated['vehicle_type'];

        try {
            $agent = DB::transaction(function () use (
                $request,
                $isJsonApi,
                $validated,
                $cleanNid,
                $personalEmail,
                $applicantName,
                $applicantPhone,
                $vehicleType,
                $profilePicUrl,
                $nidDocUrl,
                $licenseDocUrl,
                $eduDocUrl,
                $cvDocUrl
            ) {
                if ($isJsonApi && $request->user()) {
                    $user = $request->user();
                } else {
                    // Create an isolated inactive User record placeholder until Admin approves & assigns `@meramotbd.com`
                    $placeholderEmail = 'pending.' . Str::uuid() . '@pending.meramot.internal';
                    $user = User::create([
                        'name' => $applicantName,
                        'username' => 'pending_rider_' . strtolower(Str::random(8)),
                        'email' => $placeholderEmail,
                        'phone' => $applicantPhone,
                        'password' => null,
                        'role' => 'delivery_agent',
                        'status' => 'suspended',
                        'is_active' => false,
                        'avatar_url' => $profilePicUrl,
                    ]);
                }

                return DeliveryAgent::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'applicant_name' => $applicantName,
                        'applicant_phone' => $applicantPhone,
                        'personal_email' => $personalEmail,
                        'nid_number' => $cleanNid,
                        'vehicle_type' => $vehicleType,
                        'license_number' => trim($validated['license_number']),
                        'profile_picture_url' => $profilePicUrl,
                        'nid_document_url' => $nidDocUrl,
                        'license_document_url' => $licenseDocUrl,
                        'education_document_url' => $eduDocUrl,
                        'cv_document_url' => $cvDocUrl,
                        'registration_status' => 'PENDING',
                        'status' => 'OFFLINE',
                        'is_active' => false,
                        'rejection_reason' => null,
                    ]
                );
            });
        } catch (\Throwable $e) {
            Log::error('[AdminDeliveryController@store] Failed to save delivery agent application', [
                'email' => $personalEmail,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            if ($isJsonApi) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Could not submit application at this moment. Please try again.',
                ], 500);
            }

            return back()
                ->withInput()
                ->withErrors([
                    'application' => 'Could not save your application due to a temporary database connection issue. Please try submitting again.',
                ]);
        }

        if ($personalEmail) {
            try {
                app(GmailApiService::class)->sendDeliveryRegistrationAcknowledgement(
                    $personalEmail,
                    $applicantName,
                    false
                );
            } catch (\Throwable $e) {
                Log::warning('[AdminDeliveryController@store] Acknowledgement email failed: ' . $e->getMessage());
            }
        }

        if ($isJsonApi) {
            return response()->json([
                'status' => 'success',
                'message' => 'Delivery agent registration submitted for admin approval.',
                'data' => $agent,
            ], 201);
        }

        return redirect('/delivery/login')
            ->with('application_submitted_popup', [
                'name' => $applicantName,
                'personal_email' => $personalEmail,
            ])
            ->with(
                'success',
                "Application submitted. Updates will be emailed to {$personalEmail}."
            );
    }

    /* ------------------------------------------------------------------------
     * BLOCK 4: Check Application Status (Personal Email -> OTP -> Status View)
     * Routes:
     *   - GET  /delivery-agent/status
     *   - POST /delivery-agent/status/send-otp
     *   - POST /delivery-agent/status/verify-otp
     *   - POST /delivery-agent/status/reset
     *   - POST /delivery-agent/status/resend-credentials
     * ------------------------------------------------------------------------ */
    private function resolveVerifiedStatusAgent(Request $request): ?DeliveryAgent
    {
        $verifiedAgentId = (int) $request->session()->get('delivery_status_verified_agent_id', 0);
        $verifiedAt = (int) $request->session()->get('delivery_status_verified_at', 0);

        if ($verifiedAgentId <= 0 || $verifiedAt <= 0 || (now()->timestamp - $verifiedAt) > 1800) {
            if ($verifiedAgentId > 0 || $verifiedAt > 0) {
                $request->session()->forget([
                    'delivery_status_verified_agent_id',
                    'delivery_status_verified_at',
                    'delivery_status_pending_email',
                ]);
            }

            return null;
        }

        $agent = DeliveryAgent::with('user')->find($verifiedAgentId);
        if (!$agent) {
            $request->session()->forget([
                'delivery_status_verified_agent_id',
                'delivery_status_verified_at',
                'delivery_status_pending_email',
            ]);

            return null;
        }

        return $agent;
    }

    public function showStatus(Request $request)
    {
        $hadVerifiedId = $request->session()->has('delivery_status_verified_agent_id');
        $agent = $this->resolveVerifiedStatusAgent($request);

        if ($hadVerifiedId && !$agent) {
            return redirect('/delivery-agent/status')
                ->with('warning', 'Session expired or application unavailable. Verify your personal email again.');
        }

        $pendingEmail = $request->session()->get('delivery_status_pending_email');

        return response()
            ->view('delivery.status', compact('agent', 'pendingEmail'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function sendStatusOtp(Request $request, GmailApiService $gmailService)
    {
        $validated = $request->validate([
            'personal_email' => 'required|email|max:150',
        ]);

        $personalEmail = strtolower(trim($validated['personal_email']));

        $agent = DeliveryAgent::with('user')
            ->whereRaw('LOWER(personal_email) = ?', [$personalEmail])
            ->first();

        if (!$agent) {
            return back()->withInput()->withErrors([
                'personal_email' => 'No delivery partner application found for this email.',
            ]);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $agent->update([
            'status_otp_code' => $otp,
            'status_otp_expires_at' => now()->addMinutes(10),
        ]);
        Cache::forget("delivery_status_otp_attempts:{$agent->id}");

        $applicantName = $agent->applicant_name ?: ($agent->user?->name ?? 'Delivery Partner Applicant');
        $gmailService->sendDeliveryStatusOtpEmail($agent->personal_email, $applicantName, $otp);

        $request->session()->forget(['delivery_status_verified_agent_id', 'delivery_status_verified_at']);
        $request->session()->put('delivery_status_pending_email', $agent->personal_email);

        return redirect('/delivery-agent/status')->with(
            'success',
            "6-digit verification code sent to {$agent->personal_email}."
        );
    }

    public function verifyStatusOtp(Request $request)
    {
        $validated = $request->validate([
            'personal_email' => 'nullable|email|max:150',
            'otp' => 'required|string|max:12',
        ]);

        $email = strtolower(trim((string) ($validated['personal_email'] ?? $request->session()->get('delivery_status_pending_email', ''))));
        if ($email === '') {
            return redirect('/delivery-agent/status')->withErrors([
                'personal_email' => 'Enter your personal email to receive a verification code.',
            ]);
        }

        $agent = DeliveryAgent::with('user')
            ->whereRaw('LOWER(personal_email) = ?', [$email])
            ->first();

        if (!$agent) {
            $request->session()->forget(['delivery_status_pending_email', 'delivery_status_verified_agent_id', 'delivery_status_verified_at']);
            return redirect('/delivery-agent/status')->withErrors([
                'personal_email' => 'Application not found or removed.',
            ]);
        }

        $otp = InputSanitizer::sanitizeDigits($validated['otp']) ?? '';
        $attemptKey = "delivery_status_otp_attempts:{$agent->id}";

        if (strlen($otp) !== 6 || !$agent->status_otp_code || !hash_equals((string) $agent->status_otp_code, $otp)) {
            $attempts = (int) Cache::get($attemptKey, 0) + 1;
            Cache::put($attemptKey, $attempts, now()->addMinutes(10));

            if ($attempts >= 5) {
                $agent->update([
                    'status_otp_code' => null,
                    'status_otp_expires_at' => null,
                ]);
                Cache::forget($attemptKey);

                return back()->withErrors([
                    'otp' => 'Too many failed attempts. Request a new 6-digit code.',
                ]);
            }

            return back()->withErrors([
                'otp' => 'Invalid 6-digit verification code.',
            ]);
        }

        if (!$agent->status_otp_expires_at || $agent->status_otp_expires_at->isPast()) {
            return back()->withErrors([
                'otp' => 'This code has expired. Click Resend OTP.',
            ]);
        }

        Cache::forget($attemptKey);
        $agent->update([
            'status_otp_code' => null,
            'status_otp_expires_at' => null,
        ]);

        $request->session()->regenerate();
        $request->session()->forget('delivery_status_pending_email');
        $request->session()->put('delivery_status_verified_agent_id', $agent->id);
        $request->session()->put('delivery_status_verified_at', now()->timestamp);

        return redirect('/delivery-agent/status')->with('success', 'Personal email verified.');
    }

    public function resetStatusCheck(Request $request)
    {
        $request->session()->forget([
            'delivery_status_pending_email',
            'delivery_status_verified_agent_id',
            'delivery_status_verified_at',
        ]);

        return redirect('/delivery-agent/status');
    }

    /**
     * "Forgot Company Credentials" — Re-emails the exact one-time `@meramotbd.com`
     * company email & password to the OTP-verified personal email without mutating them.
     */
    public function resendCompanyCredentials(Request $request, GmailApiService $gmailService)
    {
        $agent = $this->resolveVerifiedStatusAgent($request);
        if (!$agent) {
            return redirect('/delivery-agent/status')->with('warning', 'Verify your personal email via OTP first.');
        }

        if ($agent->registration_status !== 'APPROVED' || empty($agent->company_email) || empty($agent->company_password_vault)) {
            return redirect('/delivery-agent/status')->with('warning', 'Company credentials are issued after application approval.');
        }

        try {
            $plainPassword = Crypt::decryptString($agent->company_password_vault);
        } catch (\Throwable $e) {
            return redirect('/delivery-agent/status')->with('error', 'Unable to retrieve stored credentials. Contact Delivery Operations.');
        }

        $recipientEmail = $agent->personal_email ?: $agent->user?->email;
        $applicantName = $agent->applicant_name ?: ($agent->user?->name ?? 'Delivery Partner');

        $gmailService->sendDeliveryApprovalCredentials(
            $recipientEmail,
            $applicantName,
            $agent->company_email,
            $plainPassword,
            true
        );

        return redirect('/delivery-agent/status')
            ->with('credentials_emailed_popup', $recipientEmail)
            ->with(
                'success',
                "Company credentials sent to {$recipientEmail}."
            );
    }

    /* ------------------------------------------------------------------------
     * BLOCK 5: Unlimited Application Editing (`REJECTED` -> `PENDING`)
     * Routes:
     *   - GET  /delivery-agent/edit
     *   - POST /delivery-agent/edit
     * ------------------------------------------------------------------------ */
    public function editApplication(Request $request)
    {
        $agent = $this->resolveVerifiedStatusAgent($request);
        if (!$agent) {
            return redirect('/delivery-agent/status')
                ->with('warning', 'Verify your personal email via OTP first.');
        }

        if ($agent->registration_status === 'APPROVED') {
            return redirect('/delivery-agent/status')
                ->with('warning', 'Approved applications cannot be edited. Sign in with your @meramotbd.com credentials.');
        }

        return response()
            ->view('delivery.edit', compact('agent'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function updateApplication(Request $request, GmailApiService $gmailService)
    {
        $agent = $this->resolveVerifiedStatusAgent($request);
        if (!$agent) {
            return redirect('/delivery-agent/status')
                ->with('warning', 'Verify your personal email via OTP first.');
        }

        if ($agent->registration_status === 'APPROVED') {
            return redirect('/delivery-agent/status')
                ->with('warning', 'Approved profiles cannot be modified here.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'required|string|max:30',
            'nid_number' => 'required|string|max:30',
            'vehicle_type' => 'required|in:motorcycle,bicycle,scooter,van,car',
            'license_number' => 'required|string|max:50',
            'profile_picture' => 'nullable|file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/avif|max:15360',
            'nid_document' => 'nullable|file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/avif,application/pdf|max:15360',
            'license_document' => 'nullable|file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/avif,application/pdf|max:15360',
            'education_document' => 'nullable|file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/avif,application/pdf|max:15360',
            'cv_document' => 'nullable|file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/avif,application/pdf|max:15360',
        ]);

        $previousStatus = $agent->registration_status;
        $applicantName = trim($validated['name']);
        $applicantPhone = InputSanitizer::sanitizePhone($validated['phone']);
        if (!InputSanitizer::isValidBdPhone($applicantPhone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid Bangladeshi mobile number (e.g. 01712345678).',
            ]);
        }

        $cleanNid = InputSanitizer::sanitizeDigits($validated['nid_number']);
        if (!InputSanitizer::isValidNid($cleanNid)) {
            throw ValidationException::withMessages([
                'nid_number' => 'National ID must be 10, 13, or 17 digits (numbers only).',
            ]);
        }

        $vehicleType = $validated['vehicle_type'] === 'car' ? 'van' : $validated['vehicle_type'];

        $profilePicUrl = $this->encodeUploadedDocument($request, 'profile_picture', $agent->profile_picture_url);
        $nidDocUrl = $this->encodeUploadedDocument($request, 'nid_document', $agent->nid_document_url);
        $licenseDocUrl = $this->encodeUploadedDocument($request, 'license_document', $agent->license_document_url);
        $eduDocUrl = $this->encodeUploadedDocument($request, 'education_document', $agent->education_document_url);
        $cvDocUrl = $this->encodeUploadedDocument($request, 'cv_document', $agent->cv_document_url);

        try {
            DB::transaction(function () use (
                $agent,
                $applicantName,
                $applicantPhone,
                $cleanNid,
                $validated,
                $vehicleType,
                $profilePicUrl,
                $nidDocUrl,
                $licenseDocUrl,
                $eduDocUrl,
                $cvDocUrl
            ) {
                // Whenever a REJECTED (or PENDING) application is edited, it goes back to PENDING for Admin review
                $agent->update([
                    'applicant_name' => $applicantName,
                    'applicant_phone' => $applicantPhone,
                    'nid_number' => $cleanNid,
                    'vehicle_type' => $vehicleType,
                    'license_number' => trim($validated['license_number']),
                    'profile_picture_url' => $profilePicUrl,
                    'nid_document_url' => $nidDocUrl,
                    'license_document_url' => $licenseDocUrl,
                    'education_document_url' => $eduDocUrl,
                    'cv_document_url' => $cvDocUrl,
                    'registration_status' => 'PENDING',
                    'rejection_reason' => null,
                ]);

                if ($agent->user) {
                    $agent->user->update([
                        'name' => $applicantName,
                        'phone' => $applicantPhone,
                        'avatar_url' => $profilePicUrl ?: $agent->user->avatar_url,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('[AdminDeliveryController@updateApplication] Failed to update delivery agent application', [
                'agent_id' => $agent->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'application' => 'Could not save your application updates due to a temporary database connection issue. Please try again.',
                ]);
        }

        if ($agent->personal_email) {
            try {
                $gmailService->sendDeliveryRegistrationAcknowledgement(
                    $agent->personal_email,
                    $applicantName,
                    true
                );
            } catch (\Throwable $e) {
                Log::warning('[AdminDeliveryController@updateApplication] Acknowledgement email failed: ' . $e->getMessage());
            }
        }

        AuditLog::create([
            'actor_id' => $agent->user_id,
            'action' => 'delivery_agent_application_edited',
            'target_resource' => "delivery_agents:{$agent->id}",
            'payload_before' => ['registration_status' => $previousStatus],
            'payload_after' => ['registration_status' => 'PENDING'],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return redirect('/delivery-agent/status')->with(
            'success',
            $previousStatus === 'REJECTED'
                ? 'Application updated and re-submitted! Your status is now back to PENDING for Admin approval.'
                : 'Your application details and documents have been updated (Status: PENDING).'
        );
    }

    /* ------------------------------------------------------------------------
     * BLOCK 6: Admin Approve Delivery Agent Application (One-Time Credential Gen)
     * Original Author: Md. Nafiul Islam [22299035] (Commit c6c0781)
     * Route: POST /admin/delivery-agents/{id}/approve
     * ------------------------------------------------------------------------ */
    public function approve($id)
    {
        $agent = DeliveryAgent::with('user')->findOrFail($id);
        $previousStatus = $agent->registration_status;

        $sourcePersonalEmail = $agent->personal_email
            ?: ($agent->user && !str_ends_with((string) $agent->user->email, '.meramot.internal') ? $agent->user->email : null)
            ?: ('rider' . $agent->id . '@gmail.com');

        // Strictly generate `@meramotbd.com` company email & password ONCE on first approval
        $credentialsNewlyGenerated = empty($agent->company_email) || empty($agent->company_password_vault);

        if ($credentialsNewlyGenerated) {
            $companyEmail = $this->generateUniqueCompanyEmail($sourcePersonalEmail, $agent->id, $agent->user_id);
            $plainPassword = $this->generateSecureCompanyPassword();
            $encryptedVault = Crypt::encryptString($plainPassword);
            $passwordHash = app()->environment('testing')
                ? Hash::make($plainPassword)
                : Hash::make($plainPassword, ['rounds' => 12]);
        } else {
            $companyEmail = $agent->company_email;
            $encryptedVault = $agent->company_password_vault;
            $plainPassword = Crypt::decryptString($agent->company_password_vault);
            $passwordHash = null;
        }

        $generatedUsername = $agent->user?->username;
        if (!$generatedUsername || str_starts_with($generatedUsername, 'pending_')) {
            $baseSlug = Str::slug(explode('@', $companyEmail)[0], '_') ?: ('rider_' . $agent->id);
            $generatedUsername = $baseSlug . '_' . $agent->id;
        }

        $applicantName = $agent->applicant_name ?: ($agent->user?->name ?? $generatedUsername);

        DB::transaction(function () use (
            $agent,
            $sourcePersonalEmail,
            $companyEmail,
            $encryptedVault,
            $passwordHash,
            $generatedUsername,
            $applicantName
        ) {
            $agent->update([
                'personal_email' => $agent->personal_email ?: $sourcePersonalEmail,
                'company_email' => $companyEmail,
                'company_password_vault' => $encryptedVault,
                'registration_status' => 'APPROVED',
                'status' => 'AVAILABLE',
                'is_active' => true,
                'rejection_reason' => null,
                'approved_at' => $agent->approved_at ?? now(),
            ]);

            if ($agent->user) {
                $userUpdates = [
                    'name' => $applicantName,
                    'phone' => $agent->applicant_phone ?: $agent->user->phone,
                    'username' => $generatedUsername,
                    'email' => $companyEmail,
                    'role' => 'delivery_agent',
                    'status' => 'active',
                    'is_active' => true,
                    'is_email_verified' => true,
                    'avatar_url' => $agent->profile_picture_url ?: $agent->user->avatar_url,
                ];
                if ($passwordHash !== null) {
                    $userUpdates['password'] = $passwordHash;
                }
                $agent->user->update($userUpdates);
            }
        });

        if ($credentialsNewlyGenerated && !empty($sourcePersonalEmail)) {
            app(GmailApiService::class)->sendDeliveryApprovalCredentials(
                $sourcePersonalEmail,
                $applicantName,
                $companyEmail,
                $plainPassword,
                false
            );
        }

        AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => 'delivery_agent_approved',
            'target_resource' => "delivery_agents:{$agent->id}",
            'payload_before' => ['registration_status' => $previousStatus],
            'payload_after' => [
                'registration_status' => 'APPROVED',
                'company_email' => $companyEmail,
                'credentials_generated' => $credentialsNewlyGenerated,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        Log::info("Delivery partner #{$agent->id} approved by Admin.", [
            'agent_id' => $agent->id,
            'company_email' => $companyEmail,
        ]);

        $msg = $credentialsNewlyGenerated
            ? "Delivery agent #{$agent->id} ({$applicantName}) approved! Company login ({$companyEmail}) generated once and emailed from meramotbd.official@gmail.com to {$sourcePersonalEmail}."
            : "Delivery partner #{$agent->id} ({$applicantName}) re-activated with existing {$companyEmail} credentials.";

        return redirect()->back()->with('success', $msg);
    }

    /* ------------------------------------------------------------------------
     * BLOCK 7: Admin Reject Delivery Agent Application with Rejection Reason
     * Original Author: Md. Nafiul Islam [22299035] (Commits c6c0781 & fdf5e6f)
     * Route: POST /admin/delivery-agents/{id}/reject
     * ------------------------------------------------------------------------ */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:3|max:1000',
        ]);

        $agent = DeliveryAgent::with('user')->findOrFail($id);
        $previousStatus = $agent->registration_status;
        $reason = trim($validated['rejection_reason']);

        $agent->update([
            'registration_status' => 'REJECTED',
            'rejection_reason' => $reason,
            'status' => 'SUSPENDED',
            'is_active' => false,
        ]);

        if ($agent->user) {
            $agent->user->update([
                'status' => 'suspended',
                'is_active' => false,
            ]);
        }

        $recipientEmail = $agent->personal_email
            ?: ($agent->user && !str_ends_with((string) $agent->user->email, '.meramot.internal') ? $agent->user->email : null);

        if ($recipientEmail) {
            app(GmailApiService::class)->sendDeliveryRejectionNotice(
                $recipientEmail,
                $agent->applicant_name ?: ($agent->user?->name ?? 'Delivery Applicant'),
                $reason
            );
        }

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'delivery_agent_rejected',
            'target_resource' => "delivery_agents:{$agent->id}",
            'payload_before' => ['registration_status' => $previousStatus],
            'payload_after' => ['registration_status' => 'REJECTED', 'rejection_reason' => $reason],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return redirect()->back()->with('warning', 'Delivery agent application rejected and reason emailed to applicant.');
    }

    /* ------------------------------------------------------------------------
     * BLOCK 8: Admin Block / Suspend Active Delivery Partner
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

    /* ------------------------------------------------------------------------
     * BLOCK 9: Admin Delete Delivery Agent Application Permanently
     * Route: DELETE /admin/delivery-agents/{id}
     * ------------------------------------------------------------------------ */
    public function destroy(Request $request, $id)
    {
        $agent = DeliveryAgent::with('user')->findOrFail($id);
        $applicantName = $agent->applicant_name ?: ($agent->user?->name ?? "Rider #{$agent->id}");
        $personalEmail = $agent->personal_email;
        $linkedUser = $agent->user;

        DB::transaction(function () use ($agent) {
            $agent->delete();
        });

        // If the linked User was a dedicated delivery_agent account, clean it up safely in its own savepoint
        if ($linkedUser && in_array($linkedUser->role, ['delivery_agent', 'delivery'], true)) {
            try {
                DB::transaction(function () use ($linkedUser) {
                    $linkedUser->delete();
                });
            } catch (QueryException $e) {
                $linkedUser->update([
                    'status' => 'deleted',
                    'is_active' => false,
                ]);
            }
        }

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'delivery_agent_deleted',
            'target_resource' => "delivery_agents:{$id}",
            'payload_before' => [
                'applicant_name' => $applicantName,
                'personal_email' => $personalEmail,
            ],
            'payload_after' => ['deleted' => true],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return redirect()->back()->with('warning', "Application for {$applicantName} permanently deleted from portal.");
    }

    /* ------------------------------------------------------------------------
     * BLOCK 10: Inline Admin Document Preview Endpoint
     * Route: GET /admin/delivery-agents/{id}/document/{type}
     * ------------------------------------------------------------------------ */
    public function viewDocument($id, string $type)
    {
        $agent = DeliveryAgent::with('user')->findOrFail($id);

        $raw = match ($type) {
            'profile' => $agent->profile_picture_url ?: $agent->user?->avatar_url,
            'nid' => $agent->nid_document_url,
            'license' => $agent->license_document_url,
            'education' => $agent->education_document_url,
            'cv' => $agent->cv_document_url,
            default => null,
        };

        if (!$raw) {
            abort(404, 'Document not uploaded for this applicant.');
        }

        if (preg_match('/^data:([^;]+);base64,(.+)$/s', $raw, $matches)) {
            $mime = $matches[1];
            $binary = base64_decode($matches[2], true);
            if ($binary === false) {
                abort(422, 'Corrupted document data.');
            }

            return response($binary, 200)
                ->header('Content-Type', $mime)
                ->header('Content-Disposition', 'inline; filename="applicant-' . $agent->id . '-' . $type . '"');
        }

        return redirect($raw);
    }
}
