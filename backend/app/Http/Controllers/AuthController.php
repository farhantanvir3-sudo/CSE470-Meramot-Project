<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VendorProfile;
use App\Services\GmailApiService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
     * Show Sign In Page
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect($this->redirectPathForUser(Auth::user()));
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

        if ($user && ($user->status === 'deleted' || $user->status === 'banned' || !$user->is_active)) {
            return back()->withInput($request->only('identifier'))->withErrors([
                'identifier' => 'This account is inactive or restricted.',
            ]);
        }

        if ($user && empty($user->password) && !empty($user->google_id)) {
            return back()->withInput($request->only('identifier'))->withErrors([
                'identifier' => 'This account was created using Google without a password. Please click "Sign in with Google" above.',
            ]);
        }

        if ($user && !empty($user->password) && Hash::check($credentials['password'], $user->password)) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            $defaultTarget = $this->redirectPathForUser($user);
            $intendedUrl = (string) $request->session()->pull('url.intended', '');
            $intendedPath = $intendedUrl !== '' ? (parse_url($intendedUrl, PHP_URL_PATH) ?: '') : '';

            $target = $defaultTarget;
            if ($intendedPath !== '' && !in_array($intendedPath, ['/', '/login', '/register', '/delivery/login'], true)) {
                if ($user->role === 'admin' && str_starts_with($intendedPath, '/admin')) {
                    $target = $intendedUrl;
                } elseif ($user->role === 'vendor' && str_starts_with($intendedPath, '/vendor') && $defaultTarget === '/vendor/dashboard') {
                    $target = $intendedUrl;
                } elseif ($user->role === 'customer' && !str_starts_with($intendedPath, '/admin') && !str_starts_with($intendedPath, '/vendor') && !str_starts_with($intendedPath, '/delivery')) {
                    $target = $intendedUrl;
                }
            }

            return redirect($target)
                ->with('success', 'Welcome back, ' . ($user->name ?: $user->username) . '!');
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
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

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
            'phone' => !empty($validated['phone']) ? trim($validated['phone']) : null,
            'password' => Hash::make($validated['password']),
            'role' => 'customer',
            'is_email_verified' => false,
            'email_otp_code' => $otp,
            'email_otp_expires_at' => now()->addMinutes(10),
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $gmailService->sendVerificationOtpEmail($user->email, $user->name ?? 'Meramot User', $otp);

        if ($request->boolean('verify_otp')) {
            return redirect()
                ->route('auth.verify-otp.form')
                ->with('success', 'We sent a 6-digit verification OTP to ' . $user->email . '.');
        }

        return redirect('/')->with(
            'success',
            'Account created! A 6-digit verification code has been emailed to ' . $user->email . ' from meramotbd.official@gmail.com.'
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
        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);

        if ($channel === 'email') {
            if ($user->is_email_verified) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Email is already verified.'], 400)
                    : back()->with('success', 'Your email is already verified!');
            }

            $user->update([
                'email_otp_code' => $otp,
                'email_otp_expires_at' => $expiresAt,
            ]);

            $gmailService->sendVerificationOtpEmail($user->email, $user->name ?? 'Meramot User', $otp);

            return $request->expectsJson()
                ? response()->json(['message' => 'OTP sent to email successfully'])
                : back()->with('success', 'A fresh 6-digit OTP has been sent to ' . $user->email . '.');
        }

        if ($channel === 'phone') {
            if ($user->is_phone_verified) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Phone number is already verified.'], 400)
                    : back()->with('success', 'Your phone number is already verified!');
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

            $smsService->sendVerificationOtpSms($targetPhone, $otp);

            return $request->expectsJson()
                ? response()->json(['message' => 'OTP sent to phone successfully'])
                : back()->with('success', 'A 6-digit SMS OTP has been sent to ' . $targetPhone . '.');
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
            'otp' => 'required|string|max:10',
            'channel' => 'nullable|in:email,phone',
        ]);

        $otp = trim($validated['otp']);
        $channel = $validated['channel'] ?? 'email';

        if ($channel === 'email') {
            if (!$user->email_otp_code || $user->email_otp_code !== $otp) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Invalid OTP'], 400)
                    : back()->withErrors(['otp' => 'Invalid verification OTP code. Please check your inbox.']);
            }

            if (!$user->email_otp_expires_at || $user->email_otp_expires_at->isPast()) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'OTP has expired'], 400)
                    : back()->withErrors(['otp' => 'OTP has expired. Please click Resend OTP.']);
            }

            $user->update([
                'is_email_verified' => true,
                'email_otp_code' => null,
                'email_otp_expires_at' => null,
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Email verified successfully'])
                : redirect($this->redirectPathForUser($user))
                    ->with('success', 'Your email address (' . $user->email . ') is now verified!');
        }

        if ($channel === 'phone') {
            if (!$user->phone_otp_code || $user->phone_otp_code !== $otp) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Invalid phone OTP'], 400)
                    : back()->withErrors(['otp' => 'Invalid phone OTP code. Please check your SMS messages.']);
            }

            if (!$user->phone_otp_expires_at || $user->phone_otp_expires_at->isPast()) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Phone OTP has expired'], 400)
                    : back()->withErrors(['otp' => 'Phone OTP has expired. Please request a new code.']);
            }

            $user->update([
                'is_phone_verified' => true,
                'phone_otp_code' => null,
                'phone_otp_expires_at' => null,
            ]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Phone verified successfully'])
                : back()->with('success', 'Your phone number is now verified!');
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

        if ($expectedState !== '' && $rawState !== '' && !hash_equals($expectedState, $rawState)) {
            return redirect($fallbackPage)->withErrors([
                'google' => 'Google OAuth session expired or state mismatch. Please try again.',
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
                        'google' => "You haven't signed up with Google using {$email} yet. Please click \"Sign up with Google\" below first.",
                    ]);
                }

                if (empty($user->google_id)) {
                    return redirect('/register')->withErrors([
                        'google' => "The email {$email} was not registered via Google. Please sign in with your password on the Login page, or register a Google customer account first.",
                    ]);
                }

                if ($user->status === 'banned' || !$user->is_active) {
                    return redirect('/login')->withErrors([
                        'google' => 'Your account has been restricted or deactivated.',
                    ]);
                }

                $user->update([
                    'is_email_verified' => true,
                    'avatar_url' => $user->avatar_url ?: $avatar,
                ]);

                Auth::login($user, true);
                $request->session()->regenerate();

                return redirect($this->redirectPathForUser($user))
                    ->with('success', 'Welcome back, ' . ($user->name ?: $user->email) . '! Signed in with Google.');
            }

            // -----------------------------------------------------------------
            // FLOW B: "Sign up with Google" (`intent=signup`)
            // Creates a passwordless customer account (`password = null`, `phone = null`).
            // -----------------------------------------------------------------
            if ($user && $user->status !== 'deleted') {
                if (!empty($user->google_id)) {
                    return redirect('/login')->withErrors([
                        'google' => "This Google account ({$email}) is already registered! Click \"Sign in with Google\" below to sign in.",
                    ]);
                }

                return redirect('/login')->withErrors([
                    'google' => "An account with {$email} already exists. Please sign in using your username and password.",
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

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect('/')
                ->with('success', 'Signed up with Google as ' . $user->name . '! You can add or edit your phone number and delivery address anytime in your Profile.');
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
            Auth::login($user);
            $request->session()->regenerate();
            return redirect($this->redirectPathForUser($user, $role))
                ->with('success', 'Signed in to demo portal as ' . $user->name . '!');
        }

        return redirect('/login')->with('error', 'Demo user not found. Please run database seeders.');
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
