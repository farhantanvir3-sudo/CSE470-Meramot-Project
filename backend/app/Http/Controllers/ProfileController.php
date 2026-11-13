<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Services\MediaUploadService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ============================================================================
 * Customer & User Profile Dashboard Controller
 * ============================================================================
 * Assigned Developer: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Supports:
 *   - Viewing & editing profile details anytime (`name`, `phone`, `avatar`,
 *     and delivery `address`, `city`, `area`, `latitude`, `longitude`).
 *   - Dual Delivery Address entry: interactive OpenStreetMap Location Picker
 *     OR manual street/area/city input & confirmation.
 *   - Seamless post-signup phone & delivery address completion for passwordless
 *     Google OAuth customers before or during repair/parts ordering.
 *   - Account deletion with safe relational fallback.
 * ============================================================================
 */
class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthorized'], 401)
                : redirect()->route('login');
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'user' => $this->serializeProfile($user),
            ]);
        }

        if (in_array($user->role, ['delivery_agent', 'delivery'], true)) {
            return app(DeliveryPortalController::class)->profile($request);
        }

        $rawFirst = trim((string) ($user->name ?: $user->username ?: 'User'));
        $firstName = explode(' ', $rawFirst)[0] ?: 'User';

        return view('profile.show', compact('user', 'firstName'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthorized'], 401)
                : redirect()->route('login');
        }

        if (in_array($user->role, ['delivery_agent', 'delivery'], true)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Delivery partner company credentials and profile details cannot be modified by riders.'], 403)
                : redirect('/delivery/profile')->with('warning', 'Delivery partner credentials and profile details are managed by Meramot Operations.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'area' => 'nullable|string|max:120',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'avatar' => 'nullable|file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/avif|max:15360',
            'avatar_url' => 'nullable|string',
        ]);

        $phone = isset($validated['phone']) ? trim($validated['phone']) : null;
        if ($phone === '') {
            $phone = null;
        }

        if ($phone !== null && !preg_match('/^(?:\+?8801|01)[3-9]\d{8}$/', str_replace([' ', '-'], '', $phone))) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid Bangladeshi mobile number (e.g. 01712345678 or +8801712345678).',
            ]);
        }

        $normalizedPhone = $phone !== null ? str_replace([' ', '-'], '', $phone) : null;
        $phoneChanged = $normalizedPhone !== $user->phone;

        $avatarUrl = $user->avatar_url;
        if ($request->hasFile('avatar')) {
            $avatarUrl = MediaUploadService::fromRequest($request, 'avatar', $avatarUrl, 800, 82);
        } elseif (array_key_exists('avatar_url', $validated) && !empty($validated['avatar_url'])) {
            $avatarUrl = trim($validated['avatar_url']);
        }

        $address = isset($validated['address']) && trim($validated['address']) !== ''
            ? trim($validated['address'])
            : null;
        $city = isset($validated['city']) && trim($validated['city']) !== ''
            ? trim($validated['city'])
            : ($address ? 'Dhaka' : $user->city);
        $area = isset($validated['area']) && trim($validated['area']) !== ''
            ? trim($validated['area'])
            : null;

        $lat = array_key_exists('latitude', $validated) && $validated['latitude'] !== null && $validated['latitude'] !== ''
            ? (float) $validated['latitude']
            : ($address === null ? null : $user->latitude);
        $lng = array_key_exists('longitude', $validated) && $validated['longitude'] !== null && $validated['longitude'] !== ''
            ? (float) $validated['longitude']
            : ($address === null ? null : $user->longitude);

        $user->update([
            'name' => trim($validated['name']),
            'phone' => $normalizedPhone,
            'is_phone_verified' => $phoneChanged ? false : $user->is_phone_verified,
            'address' => $address,
            'city' => $city,
            'area' => $area,
            'latitude' => $lat,
            'longitude' => $lng,
            'avatar_url' => $avatarUrl,
        ]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Profile updated.',
                'user' => $this->serializeProfile($user->fresh()),
            ]);
        }

        return redirect()->route('profile.show')->with('success', 'Profile updated successfully! Your phone and delivery address are saved for future orders.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthorized'], 401)
                : redirect()->route('login');
        }

        if ($user->role === 'admin') {
            return $request->expectsJson()
                ? response()->json(['message' => 'Platform administrator accounts cannot be self-deleted.'], 403)
                : redirect()->route('profile.show')->with('error', 'Platform administrator accounts cannot be self-deleted.');
        }

        if (in_array($user->role, ['delivery_agent', 'delivery'], true)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Delivery partner company accounts are managed by Meramot Operations.'], 403)
                : redirect('/delivery/profile')->with('warning', 'Delivery partner company accounts are managed by Meramot Operations.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        try {
            DB::transaction(function () use ($user) {
                $user->delete();
            });
        } catch (QueryException $e) {
            // Savepoint rolled back cleanly; preserve ledger/order referential integrity while deactivating & scrubbing PII
            $user->update([
                'name' => 'Deleted User',
                'phone' => null,
                'address' => null,
                'area' => null,
                'latitude' => null,
                'longitude' => null,
                'avatar_url' => null,
                'google_id' => null,
                'status' => 'deleted',
                'is_active' => false,
            ]);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Profile deleted']);
        }

        return redirect('/')->with('success', 'Your profile has been deleted.');
    }

    /**
     * Mark in-app bell notifications as seen or read.
     * Route: POST /notifications/mark-read
     */
    public function markNotificationsRead(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthorized'], 401)
                : redirect()->route('login');
        }

        if ($request->boolean('popup_only')) {
            UserNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->whereNull('popup_shown_at')
                ->update(['popup_shown_at' => now()]);
        } else {
            $query = UserNotification::where('user_id', $user->id)->where('is_read', false);
            if ($request->filled('notification_id')) {
                $query->where('id', (int) $request->input('notification_id'));
            }
            $query->update([
                'is_read' => true,
                'read_at' => now(),
                'popup_shown_at' => now(),
            ]);
        }

        $unreadCount = UserNotification::where('user_id', $user->id)->where('is_read', false)->count();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'unread_count' => $unreadCount,
            ]);
        }

        return back()->with('success', 'Notifications marked as read.');
    }

    private function serializeProfile($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatarUrl' => $user->avatar_url,
            'address' => $user->address,
            'city' => $user->city,
            'area' => $user->area,
            'latitude' => $user->latitude ? (float) $user->latitude : null,
            'longitude' => $user->longitude ? (float) $user->longitude : null,
            'authProvider' => !empty($user->google_id) ? 'google' : ($user->auth_provider ?? 'local'),
            'isEmailVerified' => (bool) $user->is_email_verified,
            'isPhoneVerified' => (bool) $user->is_phone_verified,
        ];
    }
}
