<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VendorProfile;
use App\Services\GmailApiService;
use App\Services\SmsService;
use App\Support\InputSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Determine role-aware landing destination after sign-in.
     */
    private function redirectPathForUser(User $user, ?string $requestedRole = null): string
    {
        if ($requestedRole === 'delivery_admin') {
            return '/admin/delivery-agents/pending';
        }

        return match ($user->role) {
            'admin' => '/admin',
            'vendor' => $this->resolveVendorRedirect($user),
            'delivery_agent', 'delivery' => '/delivery',
            default => '/',
        };
    }

    public function resolveVendorRedirect(User $user): string
    {
        $profile = VendorProfile::with('shops')->where('user_id', $user->id)->first();
        if (!$profile) {
            return '/vendor/apply';
        }
        if ($profile->verification_status !== 'approved') {
            return '/vendor/onboarding';
        }

        $primaryShop = $profile->shops->sortBy('id')->first();
        if ($primaryShop && !$primaryShop->setup_complete && $primaryShop->inspection_fee === null) {
            return '/vendor/setup-shop';
        }

        return '/vendor/dashboard';
    }

    private function generateUniqueUsername(string $seed, string $fallback = 'user'): string
    {
        $base = Str::slug($seed, '_') ?: $fallback;
        $candidate = $base;
        $attempt = 1;

        while (User::where('username', $candidate)->exists()) {
            $candidate = $base . '_' . ($attempt <= 5 ? $attempt : random_int(100, 9999));
            $attempt++;
            if ($attempt > 15) {
                $candidate = $base . '_' . strtolower(Str::random(6));
                break;
            }
        }

        return $candidate;
    }

    /**
     * Clear transient OTP/recovery session state on authentication boundaries.
     */
    public static function clearTransientAuthSession(Request $request): void
    {
        $request->session()->forget([
            'password_reset_auth',
            'recovery_email',
            'forgot_password_sent_email',
            'forgot_password_pending',
            'delivery_status_verified_agent_id',
            'delivery_status_verified_at',
            'delivery_status_pending_email',
        ]);
    }

    /**
     * Show Sign In Page
     */
    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            $authUser = Auth::user();
            if (!$request->boolean('forgot') || in_array($authUser->role, ['delivery', 'delivery_agent'], true)) {
                return redirect($this->redirectPathForUser($authUser));
            }
        }

        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Handle Sign In Submission
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = trim($credentials['identifier']);
        if (str_ends_with(strtolower($identifier), '@meramotbd.com')) {
            return back()->withInput($request->only('identifier'))->withErrors([
                'identifier' => 'Delivery partner @meramotbd.com accounts must sign in through "Delivery partner login" below.',
            ]);
        }

        $fieldType = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($fieldType, $fieldType === 'email' ? strtolower($identifier) : $identifier)->first();

        if ($user && in_array($user->role, ['delivery_agent', 'delivery'], true)) {
            return back()->withInput($request->only('identifier'))->withErrors([
                'identifier' => 'Delivery partner accounts use the dedicated "Delivery partner login" page.',
            ]);
        }

        if ($user && ($user->status === 'deleted' || ($user->status !== 'banned' && !$user->is_active))) {
            return back()->withInput($request->only('identifier'))->withErrors([
                'identifier' => 'This account is inactive or deactivated.',
            ]);
        }

        if ($user && empty($user->password) && !empty($user->google_id)) {
            return back()->withInput($request->only('identifier'))->withErrors([
                'identifier' => 'This account was created using Google without a password. Please click "Sign in with Google" above.',
            ]);
        }

        if ($user && !empty($user->password) && Hash::check($credentials['password'], $user->password)) {
            self::clearTransientAuthSession($request);
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            if ($user->isBanned()) {
                $banNote = $user->ban_reason ?: 'Use of offensive language in reviews';
                return redirect('/requests')->with(
                    'warning',
                    "Account Restricted ({$banNote}): Ordering is disabled, while ongoing and past orders remain accessible."
                );
            }

            $defaultTarget = $this->redirectPathForUser($user);
            $intendedUrl = (string) $request->session()->pull('url.intended', '');
            $parsedIntended = $intendedUrl !== '' ? parse_url($intendedUrl) : null;
            $intendedHost = $parsedIntended['host'] ?? null;
            $intendedPath = $parsedIntended['path'] ?? '';
            $intendedQuery = $parsedIntended['query'] ?? '';
            $hostMatches = $intendedHost === null || strcasecmp($intendedHost, $request->getHost()) === 0;
            $safePath = $intendedPath !== '' && str_starts_with($intendedPath, '/') && !str_starts_with($intendedPath, '//');

            $target = $defaultTarget;
            if ($hostMatches && $safePath && !in_array($intendedPath, ['/', '/login', '/register', '/delivery/login'], true)) {
                $safeIntended = $intendedPath . ($intendedQuery !== '' ? '?' . $intendedQuery : '');
                if ($user->role === 'admin' && str_starts_with($intendedPath, '/admin')) {
                    $target = $safeIntended;
                } elseif ($user->role === 'vendor' && str_starts_with($intendedPath, '/vendor') && $defaultTarget === '/vendor/dashboard') {
                    $target = $safeIntended;
                } elseif ($user->role === 'customer' && !str_starts_with($intendedPath, '/admin') && !str_starts_with($intendedPath, '/vendor') && !str_starts_with($intendedPath, '/delivery')) {
                    $target = $safeIntended;
                }
            }

            return redirect($target)
                ->with('success', 'Signed in. Welcome back, ' . ($user->name ?: $user->username) . '.');
        }

        return back()->withInput($request->only('identifier'))->withErrors([
            'identifier' => 'Invalid email/username or password credentials.',
        ]);
    }

    /**
     * Show Sign Up Page
     */
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect($this->redirectPathForUser(Auth::user()));
        }

        return response()
            ->view('auth.register')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Handle Local Password Sign Up Submission + Dispatch 6-Digit Email Verification OTP
     */
    public function register(Request $request, GmailApiService $gmailService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:50|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $cleanPhone = InputSanitizer::sanitizePhone($validated['phone'] ?? null);
        if ($cleanPhone !== null && !InputSanitizer::isValidBdPhone($cleanPhone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid Bangladeshi mobile number (e.g. 01712345678).',
            ]);
        }

        $otp = (string) random_int(100000, 999999);
        $email = strtolower(trim($validated['email']));
        $username = !empty($validated['username'])
            ? trim($validated['username'])
            : $this->generateUniqueUsername($validated['name'], 'customer');

        $user = User::create([
            'name' => trim($validated['name']),
            'username' => $username,
            'email' => $email,
            'auth_provider' => 'local',
            'phone' => $cleanPhone,
            'password' => Hash::make($validated['password']),
            'role' => 'customer',
            'is_email_verified' => false,
            'email_otp_code' => $otp,
            'email_otp_expires_at' => now()->addMinutes(10),
            'is_active' => true,
        ]);

        self::clearTransientAuthSession($request);
        Auth::login($user);
        $request->session()->regenerate();

        $gmailService->sendVerificationOtpEmail($user->email, $user->name ?? 'Meramot User', $otp);

        if ($request->boolean('verify_otp')) {
            return redirect()
                ->route('auth.verify-otp.form')
                ->with('success', '6-digit verification code sent to ' . $user->email . '.');
        }

        return redirect('/')->with(
            'success',
            'Account created. Verification code sent to ' . $user->email . '.'
        );
    }

    /**
     * Show Email OTP Verification Page (`GET /verify-email-otp`)
     */
    public function showVerifyOtp(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect('/login');
        }

        return view('auth.verify-otp', compact('user'));
    }

    /**
     * Send / Resend Verification OTP (`POST /verify-email-otp/send` & `POST /api/verification/send-otp`)
     */
    public function sendVerificationOtp(Request $request, GmailApiService $gmailService, SmsService $smsService)
    {
        $user = $request->user();
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthorized'], 401)
                : redirect('/login');
        }

        $channel = $request->input('channel', 'email');
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        if ($channel === 'email') {
            if ($user->is_email_verified) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Email is already verified.'], 400)
                    : back()->with('success', 'Your email is already verified.');
            }

            $user->update([
                'email_otp_code' => $otp,
                'email_otp_expires_at' => $expiresAt,
            ]);
            Cache::forget("verify_otp_attempts:email:{$user->id}");

            $gmailService->sendVerificationOtpEmail($user->email, $user->name ?? 'Meramot User', $otp);

            return $request->expectsJson()
                ? response()->json(['message' => 'Verification code sent to email.'])
                : back()->with('success', '6-digit verification code sent to ' . $user->email . '.');
        }

        if ($channel === 'phone') {
            if ($user->is_phone_verified) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Phone number is already verified.'], 400)
                    : back()->with('success', 'Your phone number is already verified.');
            }

            $targetPhone = trim((string) ($user->phone ?: optional($user->vendorProfile)->phone ?: ''));
            if ($targetPhone === '') {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Please add a phone number first before verifying.'], 400)
                    : back()->withErrors(['otp' => 'Please save a phone number in your profile first.']);
            }

            $user->update([
                'phone' => $user->phone ?: $targetPhone,
                'phone_otp_code' => $otp,
                'phone_otp_expires_at' => $expiresAt,
            ]);
            Cache::forget("verify_otp_attempts:phone:{$user->id}");

            $smsService->sendVerificationOtpSms($targetPhone, $otp);

            return $request->expectsJson()
                ? response()->json(['message' => 'Verification code sent to phone.'])
                : back()->with('success', '6-digit verification code sent to ' . $targetPhone . '.');
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Invalid channel.'], 400)
            : back()->withErrors(['otp' => 'Invalid verification channel.']);
    }

    /**
     * Verify Submitted OTP (`POST /verify-email-otp` & `POST /api/verification/verify-otp`)
     */
    public function verifyOtp(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthorized'], 401)
                : redirect('/login');
        }

        $validated = $request->validate([
            'otp' => 'required|string|max:12',
            'channel' => 'nullable|in:email,phone',
        ]);

        $otp = InputSanitizer::sanitizeDigits($validated['otp']) ?? '';
        $channel = $validated['channel'] ?? 'email';
        if (!preg_match('/^\d{6}$/', $otp)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'OTP must be a 6-digit number.'], 422)
                : back()->withErrors(['otp' => 'OTP code must be a 6-digit number.'])->withInput();
        }

        if ($channel === 'email') {
            $attemptKey = "verify_otp_attempts:email:{$user->id}";

            if (!$user->email_otp_code || !hash_equals((string) $user->email_otp_code, $otp)) {
                $attempts = (int) Cache::get($attemptKey, 0) + 1;
                Cache::put($attemptKey, $attempts, now()->addMinutes(10));

                if ($attempts >= 5) {
                    $user->update([
                        'email_otp_code' => null,
                        'email_otp_expires_at' => null,
                    ]);
                    Cache::forget($attemptKey);

                    return $request->expectsJson()
                        ? response()->json(['message' => 'Too many incorrect attempts. Request a new code.'], 422)
                        : back()->withErrors(['otp' => 'Too many incorrect attempts. Request a new code.']);
                }

                return $request->expectsJson()
                    ? response()->json(['message' => 'Invalid verification code.'], 400)
                    : back()->withErrors(['otp' => 'Invalid verification code. Please check your inbox.']);
            }

            if (!$user->email_otp_expires_at || $user->email_otp_expires_at->isPast()) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Verification code expired. Request a new code.'], 400)
                    : back()->withErrors(['otp' => 'Verification code expired. Request a new code.']);
            }

            Cache::forget($attemptKey);
            $user->update([
                'is_email_verified' => true,
                'email_otp_code' => null,
                'email_otp_expires_at' => null,
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Email verified.'])
                : redirect($this->redirectPathForUser($user))
                    ->with('success', 'Email address (' . $user->email . ') verified.');
        }

        if ($channel === 'phone') {
            $attemptKey = "verify_otp_attempts:phone:{$user->id}";

            if (!$user->phone_otp_code || !hash_equals((string) $user->phone_otp_code, $otp)) {
                $attempts = (int) Cache::get($attemptKey, 0) + 1;
                Cache::put($attemptKey, $attempts, now()->addMinutes(10));

                if ($attempts >= 5) {
                    $user->update([
                        'phone_otp_code' => null,
                        'phone_otp_expires_at' => null,
                    ]);
                    Cache::forget($attemptKey);

                    return $request->expectsJson()
                        ? response()->json(['message' => 'Too many incorrect attempts. Request a new code.'], 422)
                        : back()->withErrors(['otp' => 'Too many incorrect attempts. Request a new code.']);
                }

                return $request->expectsJson()
                    ? response()->json(['message' => 'Invalid verification code.'], 400)
                    : back()->withErrors(['otp' => 'Invalid verification code. Please check your messages.']);
            }

            if (!$user->phone_otp_expires_at || $user->phone_otp_expires_at->isPast()) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Verification code expired. Request a new code.'], 400)
                    : back()->withErrors(['otp' => 'Verification code expired. Request a new code.']);
            }

            Cache::forget($attemptKey);
            $user->update([
                'is_phone_verified' => true,
                'phone_otp_code' => null,
                'phone_otp_expires_at' => null,
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Phone verified.'])
                : back()->with('success', 'Phone number verified.');
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Invalid channel'], 400)
            : back()->withErrors(['otp' => 'Invalid verification channel.']);
    }

    /**
     * Resolve exact Authorized Redirect URI matching Google Cloud Console (`localhost:8000` locally, Render URL in prod).
     */
    private function resolveGoogleRedirectUri(Request $request): string
    {
        $host = $request->getHost();
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            $port = $request->getPort();
            $portSuffix = ($port && !in_array((int) $port, [80, 443], true)) ? ':' . $port : ':8000';
            return 'http://localhost' . $portSuffix . '/auth/google/callback';
        }

        $configured = trim((string) config('services.google.redirect', env('GOOGLE_REDIRECT_URI', '')));
        if ($configured !== '' && !str_contains($configured, 'localhost')) {
            return $configured;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/auth/google/callback';
    }

    /**
     * Redirect User to Google OAuth 2.0 Consent Screen (`GET /auth/google?intent=login|signup`)
     */
    public function redirectToGoogle(Request $request)
    {
        if (Auth::check()) {
            return redirect($this->redirectPathForUser(Auth::user()));
        }

        $intent = $request->query('intent') === 'signup' ? 'signup' : 'login';

        $clientId = (string) config('services.google.client_id', env('GOOGLE_CLIENT_ID', env('GMAIL_CLIENT_ID', '')));
        if ($clientId === '') {
            return redirect($intent === 'signup' ? '/register' : '/login')->withErrors([
                'google' => 'Google OAuth Client ID is not configured in environment variables.',
            ]);
        }

        $redirectUri = $this->resolveGoogleRedirectUri($request);
        $state = $intent . ':' . Str::random(32);

        $request->session()->put('google_oauth_state', $state);
        $request->session()->put('google_oauth_intent', $intent);
        $request->session()->put('google_oauth_redirect_uri', $redirectUri);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
            'state' => $state,
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    /**
     * Handle Google OAuth 2.0 Callback (`GET /auth/google/callback`)
     * Enforces distinct Sign Up with Google (`intent=signup`) vs Sign In with Google (`intent=login`).
     */
    public function handleGoogleCallback(Request $request)
    {
        $rawState = (string) $request->query('state', '');
        $sessionIntent = (string) $request->session()->pull('google_oauth_intent', 'login');
        $expectedState = (string) $request->session()->pull('google_oauth_state', '');

        $intent = str_starts_with($rawState, 'signup:')
            ? 'signup'
            : (str_starts_with($rawState, 'login:') ? 'login' : ($sessionIntent === 'signup' ? 'signup' : 'login'));

        $fallbackPage = $intent === 'signup' ? '/register' : '/login';

        if ($request->filled('error')) {
            return redirect($fallbackPage)->withErrors([
                'google' => 'Google authentication was cancelled or denied.',
            ]);
        }

        if ($expectedState === '' || $rawState === '' || !hash_equals($expectedState, $rawState)) {
            return redirect($fallbackPage)->withErrors([
                'google' => 'Google sign-in session expired. Please try again.',
            ]);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect($fallbackPage)->withErrors([
                'google' => 'Missing authorization code from Google.',
            ]);
        }

        $clientId = (string) config('services.google.client_id', env('GOOGLE_CLIENT_ID', env('GMAIL_CLIENT_ID', '')));
        $clientSecret = (string) config('services.google.client_secret', env('GOOGLE_CLIENT_SECRET', env('GMAIL_CLIENT_SECRET', '')));
        $redirectUri = (string) $request->session()->pull(
            'google_oauth_redirect_uri',
            $this->resolveGoogleRedirectUri($request)
        );

        try {
            $tokenResp = Http::asForm()->timeout(12)->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            if (!$tokenResp->successful()) {
                return redirect($fallbackPage)->withErrors([
                    'google' => 'Could not exchange Google authorization code. Please try again.',
                ]);
            }

            $accessToken = $tokenResp->json('access_token');
            $userInfoResp = Http::withToken($accessToken)
                ->timeout(12)
                ->get('https://www.googleapis.com/oauth2/v3/userinfo');

            if (!$userInfoResp->successful()) {
                return redirect($fallbackPage)->withErrors([
                    'google' => 'Could not retrieve Google account profile.',
                ]);
            }

            $googleUser = $userInfoResp->json();
            $email = strtolower(trim((string) ($googleUser['email'] ?? '')));
            $googleSub = trim((string) ($googleUser['sub'] ?? ''));

            if ($email === '') {
                return redirect($fallbackPage)->withErrors([
                    'google' => 'Google account did not provide a valid email address.',
                ]);
            }

            $name = trim((string) ($googleUser['name'] ?? explode('@', $email)[0]));
            $avatar = $googleUser['picture'] ?? null;

            $user = User::where('email', $email)->first();
            if (!$user && $googleSub !== '') {
                $user = User::where('google_id', $googleSub)->first();
            }

            // -----------------------------------------------------------------
            // FLOW A: "Sign in with Google" (`intent=login`)
            // Strictly requires prior "Sign up with Google" (`google_id` set).
            // -----------------------------------------------------------------
            if ($intent === 'login') {
                if (!$user || $user->status === 'deleted') {
                    return redirect('/register')->withErrors([
                        'google' => "No Meramot account was found for {$email}. Please create an account using \"Sign up with Google\" to continue.",
                    ]);
                }

                if (empty($user->google_id)) {
                    return redirect('/register')->withErrors([
                        'google' => "An account for {$email} is registered with a password. Please sign in with your password, or register a new Google account.",
                    ]);
                }

                if ($user->status !== 'banned' && !$user->is_active) {
                    return redirect('/login')->withErrors([
                        'google' => 'Your account has been deactivated. Please contact Meramot Support.',
                    ]);
                }

                $user->update([
                    'is_email_verified' => true,
                    'avatar_url' => $user->avatar_url ?: $avatar,
                ]);

                self::clearTransientAuthSession($request);
                Auth::login($user, true);
                $request->session()->regenerate();

                if ($user->isBanned()) {
                    $banNote = $user->ban_reason ?: 'Use of offensive language in reviews';
                    return redirect('/requests')->with(
                        'warning',
                        "Account Restricted ({$banNote}): You can view and track your ongoing and past orders, but placing new service orders or repair requests is disabled."
                    );
                }

                return redirect($this->redirectPathForUser($user))
                    ->with('success', 'Signed in with Google. Welcome back, ' . ($user->name ?: $user->email) . '.');
            }

            // -----------------------------------------------------------------
            // FLOW B: "Sign up with Google" (`intent=signup`)
            // Creates a passwordless customer account (`password = null`, `phone = null`).
            // -----------------------------------------------------------------
            if ($user && $user->status !== 'deleted') {
                if (!empty($user->google_id)) {
                    return redirect('/login')->withErrors([
                        'google' => "An account is already registered with {$email}. Please use \"Sign in with Google\" to access your account.",
                    ]);
                }

                return redirect('/login')->withErrors([
                    'google' => "An account already exists for {$email}. Please sign in using your username and password.",
                ]);
            }

            $resolvedGoogleId = $googleSub !== '' ? $googleSub : ('google_' . md5($email . '_' . Str::random(8)));

            if ($user && $user->status === 'deleted') {
                // Re-activate previously deleted account cleanly as a fresh Google customer
                $user->update([
                    'name' => $name,
                    'google_id' => $resolvedGoogleId,
                    'auth_provider' => 'google',
                    'password' => null,
                    'phone' => null,
                    'avatar_url' => $avatar,
                    'role' => 'customer',
                    'is_email_verified' => true,
                    'is_phone_verified' => false,
                    'status' => 'active',
                    'is_active' => true,
                ]);
            } else {
                $username = $this->generateUniqueUsername($name ?: explode('@', $email)[0], 'customer');

                $user = User::create([
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'google_id' => $resolvedGoogleId,
                    'auth_provider' => 'google',
                    'password' => null,
                    'phone' => null,
                    'avatar_url' => $avatar,
                    'role' => 'customer',
                    'is_email_verified' => true,
                    'is_phone_verified' => false,
                    'status' => 'active',
                    'is_active' => true,
                ]);
            }

            self::clearTransientAuthSession($request);
            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect('/')
                ->with('success', 'Welcome to Meramot, ' . $user->name . '. Add your phone and address anytime in Profile.');
        } catch (\Throwable $e) {
            return redirect($fallbackPage)->withErrors([
                'google' => 'Google authentication failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Fast Demo Sign-In (For developer/reviewer testing across all roles)
     */
    public function demoLogin(Request $request)
    {
        $role = $request->get('role', 'customer');

        if ($role === 'delivery') {
            $user = User::whereIn('email', ['rahim.uddin@meramotbd.com', 'rider.demo@meramot.test'])->first()
                ?? User::whereIn('role', ['delivery_agent', 'delivery'])
                    ->whereHas('deliveryAgent', fn ($q) => $q->where('registration_status', 'APPROVED')->where('is_active', true))
                    ->orderBy('id')
                    ->first();
        } else {
            $email = match ($role) {
                'vendor' => 'sakib.tech@gmail.com',
                'admin' => 'admin@meramot.demo',
                'delivery_admin' => 'delivery.admin@meramot.demo',
                default => 'customer@meramot.demo',
            };
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            self::clearTransientAuthSession($request);
            Auth::login($user);
            $request->session()->regenerate();
            return redirect($this->redirectPathForUser($user, $role))
                ->with('success', 'Signed in to demo portal as ' . $user->name . '!');
        }

        return redirect('/login')->with('error', 'Demo user not found. Please run database seeders.');
    }

    /**
     * Resolve the verified password-reset user only when session state and token both match within TTL.
     */
    private function resolveAuthorizedPasswordResetUser(Request $request, string $plainToken): ?User
    {
        $auth = $request->session()->get('password_reset_auth');
        if (!is_array($auth)) {
            return null;
        }

        if ($plainToken === '' || strlen($plainToken) !== 64 || !ctype_alnum($plainToken)) {
            return null;
        }

        $tokenHash = (string) ($auth['token_hash'] ?? '');
        $userId = (int) ($auth['user_id'] ?? 0);
        $expiresAt = (int) ($auth['expires_at'] ?? 0);

        if ($tokenHash === '' || $userId <= 0 || $expiresAt < now()->timestamp) {
            return null;
        }

        if (!hash_equals($tokenHash, hash('sha256', $plainToken))) {
            return null;
        }

        $user = User::find($userId);
        if (
            !$user
            || $user->status === 'deleted'
            || ($user->status !== 'banned' && !$user->is_active)
            || in_array($user->role, ['delivery', 'delivery_agent'], true)
        ) {
            return null;
        }

        return $user;
    }

    /**
     * Show the Set New Password View (`GET /forgot-password` / `GET /reset-password`)
     * strictly gated behind prior OTP verification in the active session.
     */
    public function showForgotPassword(Request $request)
    {
        $token = trim((string) $request->query('token', ''));
        $user = $this->resolveAuthorizedPasswordResetUser($request, $token);

        if (!$user) {
            $request->session()->forget('password_reset_auth');

            $identifier = trim((string) ($request->query('identifier') ?: $request->query('email', '')));
            $loginUrl = '/login?forgot=1' . ($identifier !== '' ? '&identifier=' . urlencode($identifier) : '');

            return redirect($loginUrl)->withErrors([
                'identifier' => 'Verify your 6-digit recovery code first to set a new password.',
            ]);
        }

        return response()
            ->view('auth.forgot-password', [
                'token' => $token,
                'maskedEmail' => $this->maskRecoveryEmail($user->email),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Mask local-part of an email address before displaying in unauthenticated responses.
     */
    private function maskRecoveryEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $prefix = substr($local, 0, min(2, strlen($local)));

        return $prefix . '***@' . $domain;
    }

    /**
     * Send password recovery OTP & account credentials email (`POST /forgot-password`).
     */
    public function sendForgotPasswordEmail(Request $request, GmailApiService $gmailService)
    {
        $request->session()->forget('password_reset_auth');

        $raw = trim((string) ($request->input('identifier') ?: $request->input('email', '')));

        if ($raw === '') {
            throw ValidationException::withMessages([
                'email' => 'Enter your registered email address or username.',
            ]);
        }

        if (mb_strlen($raw) > 255) {
            throw ValidationException::withMessages([
                'email' => 'Enter a valid email address or username under 255 characters.',
            ]);
        }

        if (str_contains($raw, '@') && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Enter a valid email address or username.',
            ]);
        }

        $lookup = strtolower($raw);

        if (str_ends_with($lookup, '@meramotbd.com')) {
            throw ValidationException::withMessages([
                'email' => 'Use Delivery Partner Login (/delivery/login) to recover @meramotbd.com credentials.',
            ]);
        }

        $user = User::where(function ($query) use ($lookup) {
            $query->whereRaw('LOWER(email) = ?', [$lookup])
                ->orWhereRaw('LOWER(username) = ?', [$lookup]);
        })->first();

        $eligibleUser = $user
            && $user->status !== 'deleted'
            && ($user->status === 'banned' || $user->is_active)
            && !in_array($user->role, ['delivery', 'delivery_agent'], true);

        if ($eligibleUser) {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->update([
                'email_otp_code' => $otp,
                'email_otp_expires_at' => now()->addMinutes(15),
            ]);
            Cache::forget("pwd_reset_otp_attempts:{$user->id}");

            $safeName = htmlspecialchars($user->name ?: 'Meramot User', ENT_QUOTES, 'UTF-8');
            $safeEmail = htmlspecialchars($user->email, ENT_QUOTES, 'UTF-8');
            $safeUsername = htmlspecialchars($user->username ?: '—', ENT_QUOTES, 'UTF-8');
            $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
            $verifyPopupUrl = url('/login?forgot=1&identifier=' . urlencode($user->email));

            $html = "
                <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.65; max-width: 560px; margin: 0 auto; padding: 26px; border: 1px solid #d9e5d5; border-radius: 16px; background: #ffffff;\">
                    <h2 style=\"color: #214c34; margin-top: 0;\">Account Credentials &amp; Password Reset</h2>
                    <p style=\"font-size: 14px;\">Hello <strong>{$safeName}</strong>,</p>
                    <p style=\"font-size: 14px;\">
                        Use the 6-digit code below inside the Meramot sign-in recovery popup to verify your identity:
                    </p>
                    <div style=\"margin: 18px 0; padding: 14px 16px; border-radius: 12px; background: #f8fbf6; border: 1px solid #d9e5d5; font-size: 13px;\">
                        <div><strong>Username:</strong> {$safeUsername}</div>
                        <div><strong>Email:</strong> {$safeEmail}</div>
                    </div>
                    <div style=\"margin: 20px 0; text-align: center;\">
                        <div style=\"font-size: 11px; text-transform: uppercase; letter-spacing: 0.12em; color: #5e6d64; margin-bottom: 6px;\">6-Digit Verification Code (Expires in 15 Minutes)</div>
                        <div style=\"display: inline-block; padding: 12px 26px; border-radius: 14px; background: #eef5ea; border: 2px solid #214c34; font-family: monospace; font-size: 28px; font-weight: bold; letter-spacing: 6px; color: #163625;\">{$safeOtp}</div>
                    </div>
                    <div style=\"margin: 22px 0; text-align: center;\">
                        <a href=\"{$verifyPopupUrl}\" style=\"display: inline-block; background: #214c34; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 999px; font-weight: bold; font-size: 13px;\">
                            Verify Code on Sign In
                        </a>
                    </div>
                    <p style=\"font-size: 13px; color: #214c34;\">Sincerely,<br/><strong>Meramot Trust &amp; Security</strong></p>
                    <p style=\"margin-top: 18px; font-size: 11px; color: #7a8a80; border-top: 1px solid #e5ede2; padding-top: 12px;\">If you did not request this code, no action is needed.</p>
                </div>
            ";

            try {
                $gmailService->sendEmail($user->email, 'Meramot — Verification Code & Account Reminder', $html);
            } catch (\Throwable $e) {
                // Keep response uniform and non-enumerating
            }
        }

        $displayTarget = str_contains($lookup, '@')
            ? $lookup
            : ($eligibleUser && !empty($user->email) ? $this->maskRecoveryEmail($user->email) : $raw);
        $lookupValue = str_contains($lookup, '@') ? $lookup : $raw;
        $message = "Enter the 6-digit code sent to {$displayTarget}.";

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'email' => $displayTarget,
                'lookup' => $lookupValue,
                'message' => $message,
            ]);
        }

        return redirect('/login?forgot=1')
            ->with('forgot_password_pending', [
                'target' => $displayTarget,
                'lookup' => $lookupValue,
            ])
            ->with('success', $message);
    }

    /**
     * Verify the 6-digit recovery OTP inside the sign-in modal (`POST /forgot-password/verify-otp`)
     * and mint a short-lived single-use session token authorizing the Set New Password view.
     */
    public function verifyForgotPasswordOtp(Request $request)
    {
        $validated = $request->validate([
            'identifier' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'otp' => 'required|string|max:12',
        ]);

        $rawIdentifier = trim((string) ($validated['identifier'] ?: ($validated['email'] ?? '')));
        if ($rawIdentifier === '') {
            throw ValidationException::withMessages([
                'identifier' => 'Enter your email address or username first.',
            ]);
        }

        $otp = InputSanitizer::sanitizeDigits($validated['otp']) ?? '';
        if (!preg_match('/^\d{6}$/', $otp)) {
            throw ValidationException::withMessages([
                'otp' => 'Enter the 6-digit code sent to your email.',
            ]);
        }

        $lookup = strtolower($rawIdentifier);
        if (str_ends_with($lookup, '@meramotbd.com')) {
            throw ValidationException::withMessages([
                'identifier' => 'Use Delivery Partner Login (/delivery/login) for @meramotbd.com accounts.',
            ]);
        }

        $user = User::where(function ($query) use ($lookup) {
            $query->whereRaw('LOWER(email) = ?', [$lookup])
                ->orWhereRaw('LOWER(username) = ?', [$lookup]);
        })->first();

        $eligibleUser = $user
            && $user->status !== 'deleted'
            && ($user->status === 'banned' || $user->is_active)
            && !in_array($user->role, ['delivery', 'delivery_agent'], true);

        if (!$eligibleUser) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired 6-digit code.',
            ]);
        }

        $attemptKey = "pwd_reset_otp_attempts:{$user->id}";

        $otpMatches = !empty($user->email_otp_code)
            && hash_equals((string) $user->email_otp_code, $otp);

        if (!$otpMatches) {
            $attempts = (int) Cache::get($attemptKey, 0) + 1;
            Cache::put($attemptKey, $attempts, now()->addMinutes(15));

            if ($attempts >= 5) {
                $user->update([
                    'email_otp_code' => null,
                    'email_otp_expires_at' => null,
                ]);
                Cache::forget($attemptKey);

                throw ValidationException::withMessages([
                    'otp' => 'Too many failed attempts. Request a new 6-digit code.',
                ]);
            }

            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired 6-digit code.',
            ]);
        }

        if ($user->email_otp_expires_at && $user->email_otp_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => 'This 6-digit code has expired. Request a new code.',
            ]);
        }

        // Consume OTP immediately so it cannot be replayed
        $user->update([
            'email_otp_code' => null,
            'email_otp_expires_at' => null,
        ]);
        Cache::forget($attemptKey);

        $request->session()->regenerate();

        $plainToken = Str::random(64);
        $request->session()->put('password_reset_auth', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        $resetUrl = '/forgot-password?token=' . $plainToken;
        $message = 'Code verified. Continue to set your new password.';

        if ($request->expectsJson()) {
            return response()
                ->json([
                    'ok' => true,
                    'reset_url' => $resetUrl,
                    'message' => $message,
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        return redirect($resetUrl);
    }

    /**
     * Save the new password (`POST /forgot-password/reset`) ONLY for an OTP-verified session & token.
     */
    public function resetPasswordWithOtp(Request $request)
    {
        $token = trim((string) $request->input('reset_token', ''));
        $user = $this->resolveAuthorizedPasswordResetUser($request, $token);

        if (!$user) {
            $request->session()->forget('password_reset_auth');
            $msg = 'Reset session expired or unauthorized. Verify a new 6-digit code.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 403);
            }

            return redirect('/login?forgot=1')->withErrors([
                'identifier' => $msg,
            ]);
        }

        $validated = $request->validate([
            'password' => 'required|string|min:8|max:128|confirmed',
        ], [
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'New password confirmation does not match.',
        ]);

        $request->session()->forget('password_reset_auth');

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
            'email_otp_code' => null,
            'email_otp_expires_at' => null,
            'is_email_verified' => true,
        ])->save();

        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            $request->session()->regenerate();
            $request->session()->regenerateToken();
        }

        $message = 'Password updated. Sign in with your new password.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
            ]);
        }

        return redirect('/login')->with('success', $message);
    }

    /**
     * Log out
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'You have been logged out.');
    }
}
