<?php

namespace App\Http\Controllers;

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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'area' => 'nullable|string|max:120',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
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
            $file = $request->file('avatar');
            $mime = $file->getMimeType() ?: 'image/jpeg';
            $avatarUrl = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file->getRealPath()));
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
