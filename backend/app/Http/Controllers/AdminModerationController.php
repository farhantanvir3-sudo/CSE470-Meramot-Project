<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\ModerationLog;
use App\Models\ShopReview;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\GmailApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * FEATURE R19: Sitewide User Moderation & Content Governance Panel
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Supports deleting offensive reviews, banning offending users with email +
 * in-app bell pop-up notifications, and unbanning restricted users.
 * ============================================================================
 */
class AdminModerationController extends Controller
{
    /**
     * Show moderation and content governance dashboard.
     * Route: GET /admin/moderation
     */
    public function index()
    {
        $reviews = ShopReview::with(['customer', 'shop'])
            ->where('is_hidden', false)
            ->latest()
            ->take(60)
            ->get()
            ->sortByDesc(function (ShopReview $review) {
                $isOffensive = ShopReview::containsOffensiveLanguage($review->review_comment) ? 1 : 0;
                $ts = $review->created_at ? $review->created_at->getTimestamp() : 0;
                return sprintf('%d-%012d-%08d', $isOffensive, $ts, (int) $review->id);
            })
            ->values();

        $bannedUsers = User::where('status', 'banned')
            ->when(Schema::hasColumn('users', 'banned_at'), fn ($q) => $q->orderByDesc('banned_at'))
            ->orderByDesc('updated_at')
            ->get();

        $logs = ModerationLog::with('admin')->latest()->paginate(20);

        return view('admin.moderation', compact('logs', 'reviews', 'bannedUsers'));
    }

    /**
     * Delete a review and optionally ban the offending author for offensive language.
     * Route: POST /admin/reviews/{reviewId}/moderate
     */
    public function moderateReview(Request $request, $reviewId, GmailApiService $gmailService)
    {
        $validated = $request->validate([
            'action_type' => 'required|in:delete_only,delete_and_ban',
            'reason' => 'nullable|string|max:500',
            'redirect_to' => 'nullable|string|max:500',
        ]);

        $review = ShopReview::with(['customer', 'shop.vendorProfile'])->findOrFail($reviewId);
        $shop = $review->shop;
        $author = $review->customer;
        $banUser = $validated['action_type'] === 'delete_and_ban';
        $reason = trim((string) ($validated['reason'] ?? ''))
            ?: 'Use of offensive or inappropriate language in a workshop review, in violation of Meramot Community Guidelines.';
        $excerpt = Str::limit((string) $review->review_comment, 140);
        $admin = $request->user();

        DB::transaction(function () use ($review, $shop, $author, $banUser, $reason, $admin, $request) {
            $reviewIdVal = $review->id;
            $reviewRating = $review->rating;

            $review->delete();

            if ($shop) {
                ReviewController::recalculateShopRating($shop->fresh(['vendorProfile']));
            }

            if ($banUser && $author && $author->role !== 'admin') {
                $author->update([
                    'status' => 'banned',
                    'ban_reason' => $reason,
                    'banned_at' => now(),
                    'is_active' => true,
                ]);

                Cart::where('user_id', $author->id)->where('status', 'ACTIVE')->delete();
            }

            ModerationLog::create([
                'admin_id' => $admin->id,
                'target_type' => 'user',
                'target_id' => $author?->id ?? 0,
                'violation_type' => 'offensive_review_language',
                'action_taken' => $banUser ? 'banned_and_review_deleted' : 'review_deleted',
                'reason' => $reason,
            ]);

            AuditLog::create([
                'actor_id' => $admin->id,
                'action' => $banUser ? 'review_deleted_and_user_banned' : 'offensive_review_deleted',
                'target_resource' => "shop_reviews:{$reviewIdVal}",
                'payload_before' => [
                    'review_id' => $reviewIdVal,
                    'shop_id' => $shop?->id,
                    'author_id' => $author?->id,
                    'rating' => $reviewRating,
                ],
                'payload_after' => [
                    'deleted' => true,
                    'user_banned' => $banUser && $author && $author->role !== 'admin',
                    'reason' => $reason,
                ],
                'ip_address' => $request->ip(),
            ]);

            if ($author) {
                $shopLabel = $shop?->name ?: 'a Meramot workshop';
                $notifTitle = $banUser
                    ? 'Review Removed & Ordering Privileges Restricted'
                    : 'Review Removed by Moderation';
                $notifMsg = $banUser
                    ? "Your review on {$shopLabel} was removed and your account ordering privileges were restricted due to a policy violation ({$reason}). You may still view and track ongoing and past orders, while new bookings and repair requests remain disabled."
                    : "Your review on {$shopLabel} was removed following moderation review ({$reason}).";

                UserNotification::create([
                    'user_id' => $author->id,
                    'type' => $banUser ? 'ACCOUNT_BANNED' : 'REVIEW_REMOVED',
                    'severity' => $banUser ? 'danger' : 'warning',
                    'title' => $notifTitle,
                    'message' => $notifMsg,
                    'action_url' => '/requests',
                    'is_read' => false,
                ]);

                try {
                    DB::table('notification_logs')->insert([
                        'repair_request_id' => null,
                        'delivery_job_id' => null,
                        'channel' => 'IN_APP',
                        'recipient' => (string) $author->email,
                        'subject' => $notifTitle,
                        'body' => $notifMsg,
                        'provider' => 'MeramotBell',
                        'provider_ref' => 'bell_' . uniqid(),
                        'status' => 'SENT',
                        'sent_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    // Non-blocking audit log insert
                }
            }
        });

        if ($author && !empty($author->email)) {
            $gmailService->sendModerationSanctionEmail(
                $author->email,
                $author->name ?: 'Meramot User',
                $shop?->name ?: 'Meramot Workshop',
                $reason,
                $banUser && $author->role !== 'admin',
                $excerpt
            );
        }

        $flash = $banUser
            ? 'The offending review has been deleted, ordering privileges for this account have been restricted, and email and in-app notifications have been sent.'
            : 'The review has been deleted, the workshop rating has been recalculated, and the author has been notified via email and in-app alert.';

        $redirectTo = (string) ($validated['redirect_to'] ?? '');
        if ($redirectTo !== '' && str_starts_with($redirectTo, '/')) {
            return redirect($redirectTo)->with('success', $flash);
        }

        return back()->with('success', $flash);
    }

    /**
     * Ban a user directly from the moderation panel.
     * Route: POST /admin/users/{userId}/ban
     */
    public function banUser(Request $request, $userId, GmailApiService $gmailService)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $target = User::findOrFail($userId);
        if ($target->role === 'admin') {
            return back()->with('error', 'Administrator accounts cannot be banned.');
        }

        $reason = trim((string) ($validated['reason'] ?? ''))
            ?: 'Account restricted due to abusive or offensive language in customer reviews.';
        $admin = $request->user();

        DB::transaction(function () use ($target, $reason, $admin, $request) {
            $target->update([
                'status' => 'banned',
                'ban_reason' => $reason,
                'banned_at' => now(),
                'is_active' => true,
            ]);

            Cart::where('user_id', $target->id)->where('status', 'ACTIVE')->delete();

            ModerationLog::create([
                'admin_id' => $admin->id,
                'target_type' => 'user',
                'target_id' => $target->id,
                'violation_type' => 'offensive_review_language',
                'action_taken' => 'banned',
                'reason' => $reason,
            ]);

            AuditLog::create([
                'actor_id' => $admin->id,
                'action' => 'user_banned',
                'target_resource' => "users:{$target->id}",
                'payload_before' => ['status' => 'active'],
                'payload_after' => ['status' => 'banned', 'ban_reason' => $reason],
                'ip_address' => $request->ip(),
            ]);

            $notifTitle = 'Account Ordering Privileges Restricted';
            $notifMsg = "Your account has been restricted due to offensive language ({$reason}). You can continue viewing and tracking ongoing and past orders, while placing new service orders or repair requests is disabled.";

            UserNotification::create([
                'user_id' => $target->id,
                'type' => 'ACCOUNT_BANNED',
                'severity' => 'danger',
                'title' => $notifTitle,
                'message' => $notifMsg,
                'action_url' => '/requests',
                'is_read' => false,
            ]);
        });

        if (!empty($target->email)) {
            $gmailService->sendModerationSanctionEmail(
                $target->email,
                $target->name ?: 'Meramot User',
                'Meramot Platform',
                $reason,
                true
            );
        }

        return back()->with('success', "Account {$target->name} has been restricted from new orders and requests, and notified via email and in-app alert.");
    }

    /**
     * Unban a restricted user and restore ordering/request capabilities.
     * Route: POST /admin/users/{userId}/unban
     */
    public function unbanUser(Request $request, $userId, GmailApiService $gmailService)
    {
        $target = User::findOrFail($userId);
        $admin = $request->user();

        DB::transaction(function () use ($target, $admin, $request) {
            $prevReason = $target->ban_reason;
            $target->update([
                'status' => 'active',
                'ban_reason' => null,
                'banned_at' => null,
                'is_active' => true,
            ]);

            ModerationLog::create([
                'admin_id' => $admin->id,
                'target_type' => 'user',
                'target_id' => $target->id,
                'violation_type' => 'appeal_or_expiry',
                'action_taken' => 'unbanned',
                'reason' => 'Restriction lifted by administrator.',
            ]);

            AuditLog::create([
                'actor_id' => $admin->id,
                'action' => 'user_unbanned',
                'target_resource' => "users:{$target->id}",
                'payload_before' => ['status' => 'banned', 'ban_reason' => $prevReason],
                'payload_after' => ['status' => 'active'],
                'ip_address' => $request->ip(),
            ]);

            UserNotification::create([
                'user_id' => $target->id,
                'type' => 'ACCOUNT_UNBANNED',
                'severity' => 'success',
                'title' => 'Account Restrictions Lifted',
                'message' => 'Your Meramot service ordering and repair request privileges have been fully restored.',
                'action_url' => '/requests',
                'is_read' => false,
            ]);
        });

        if (!empty($target->email)) {
            $gmailService->sendAccountUnbannedEmail($target->email, $target->name ?: 'Meramot User');
        }

        return back()->with('success', "Account restrictions for {$target->name} have been lifted and the user has been notified.");
    }

    /**
     * Apply governance sanction on offending user (preserves legacy roadmap placeholder when posted empty).
     * Route: POST /admin/moderation/sanction
     */
    public function applySanction(Request $request, GmailApiService $gmailService)
    {
        if ($request->filled('target_user_id')) {
            return $this->banUser($request, $request->input('target_user_id'), $gmailService);
        }

        return app(ComingSoonController::class)->show($request, 'content-moderation');
    }
}
