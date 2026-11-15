<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\DisputeCase;
use App\Models\DisputeNote;
use App\Models\ModerationLog;
use App\Models\PaymentTransaction;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ShopReview;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\GmailApiService;
use App\Services\MultiChannelNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * FEATURE R19: Sitewide User Moderation, Dispute Governance & Support Desk
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Sprint: Sprint 3
 * Supports review moderation, user/workshop governance sanctions, escrow
 * dispute arbitration with optional refund settlement, and customer support.
 * ============================================================================
 */
class AdminModerationController extends Controller
{
    /**
     * Show moderation, dispute arbitration, sanction desk, and support governance dashboard.
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

        $disputes = DisputeCase::with([
            'repairRequest',
            'openedBy',
            'against',
            'payment',
            'notes.author',
            'refunds',
        ])
            ->orderByRaw("CASE WHEN status IN ('OPEN','INVESTIGATING','WAITING_EVIDENCE','WAITING_RESPONSE') THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->take(30)
            ->get();

        $supportTickets = SupportTicket::with(['user', 'shop', 'repairRequest', 'messages.author'])
            ->orderByRaw("CASE WHEN status IN ('OPEN','IN_PROGRESS','ESCALATED') THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->take(30)
            ->get();

        $sanctionableUsers = User::where('role', '!=', 'admin')
            ->orderBy('name')
            ->take(60)
            ->get();

        $sanctionableShops = RepairShop::orderBy('name')
            ->take(60)
            ->get();

        $logs = ModerationLog::with('admin')->latest()->paginate(20);

        return view('admin.moderation', compact(
            'logs',
            'reviews',
            'bannedUsers',
            'disputes',
            'supportTickets',
            'sanctionableUsers',
            'sanctionableShops'
        ));
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
     * Apply unified governance sanction on user, workshop, or review.
     * Route: POST /admin/moderation/sanction
     */
    public function applySanction(
        Request $request,
        GmailApiService $gmailService,
        MultiChannelNotificationService $notifier
    ) {
        if (!$request->filled('action') && $request->filled('action_taken')) {
            $request->merge(['action' => $request->input('action_taken')]);
        }

        if ($request->filled('target_user_id') && !$request->filled('action')) {
            return $this->banUser($request, $request->input('target_user_id'), $gmailService);
        }

        $validated = $request->validate([
            'target_type' => 'required|in:user,shop,review',
            'target_id' => 'required|integer|min:1',
            'action' => 'required|in:warn,suspend,ban,restore_user,delist_shop,restore_shop,hide_review,unhide_review',
            'violation_type' => 'nullable|string|max:100',
            'reason' => 'required|string|min:5|max:500',
        ]);

        $admin = $request->user();
        $targetType = $validated['target_type'];
        $targetId = (int) $validated['target_id'];
        $action = $validated['action'];
        $violation = trim((string) ($validated['violation_type'] ?? 'governance_policy_violation'));
        $reason = trim($validated['reason']);

        if ($targetType === 'user') {
            $targetUser = User::findOrFail($targetId);
            if ($targetUser->role === 'admin' && in_array($action, ['suspend', 'ban'], true)) {
                return back()->with('error', 'Administrator accounts cannot be suspended or banned.');
            }

            if ($action === 'ban') {
                return $this->banUser($request->merge(['reason' => $reason]), $targetUser->id, $gmailService);
            }
            if ($action === 'restore_user') {
                return $this->unbanUser($request, $targetUser->id, $gmailService);
            }

            DB::transaction(function () use ($targetUser, $action, $violation, $reason, $admin, $request) {
                if ($action === 'suspend') {
                    $targetUser->update([
                        'status' => 'banned',
                        'ban_reason' => "[Suspended] {$reason}",
                        'banned_at' => now(),
                    ]);
                    Cart::where('user_id', $targetUser->id)->where('status', 'ACTIVE')->delete();
                }

                ModerationLog::create([
                    'admin_id' => $admin->id,
                    'target_type' => 'user',
                    'target_id' => $targetUser->id,
                    'violation_type' => $violation,
                    'action_taken' => $action === 'suspend' ? 'suspended' : 'warned',
                    'reason' => $reason,
                ]);

                AuditLog::create([
                    'actor_id' => $admin->id,
                    'action' => "moderation_user_{$action}",
                    'target_resource' => "users:{$targetUser->id}",
                    'payload_after' => ['action' => $action, 'reason' => $reason],
                    'ip_address' => $request->ip() ?? '127.0.0.1',
                ]);
            });

            $notifier->notifyUser(
                user: $targetUser,
                eventType: strtoupper("MODERATION_{$action}"),
                title: $action === 'suspend' ? 'Account Temporarily Suspended' : 'Official Governance Warning',
                message: $reason,
                actionUrl: '/support',
                severity: $action === 'suspend' ? 'danger' : 'warning'
            );

            return redirect('/admin/moderation')->with('success', "Sanction ({$action}) applied to {$targetUser->name} and logged.");
        }

        if ($targetType === 'shop') {
            $shop = RepairShop::with('vendorProfile.user')->findOrFail($targetId);
            $delist = $action === 'delist_shop';

            DB::transaction(function () use ($shop, $delist, $violation, $reason, $admin, $request) {
                $shop->update([
                    'is_public' => !$delist,
                    'is_active' => !$delist,
                ]);

                ModerationLog::create([
                    'admin_id' => $admin->id,
                    'target_type' => 'shop',
                    'target_id' => $shop->id,
                    'violation_type' => $violation,
                    'action_taken' => $delist ? 'shop_delisted' : 'shop_restored',
                    'reason' => $reason,
                ]);

                AuditLog::create([
                    'actor_id' => $admin->id,
                    'action' => $delist ? 'shop_delisted' : 'shop_restored',
                    'target_resource' => "repair_shops:{$shop->id}",
                    'payload_after' => ['is_public' => !$delist, 'reason' => $reason],
                    'ip_address' => $request->ip() ?? '127.0.0.1',
                ]);
            });

            if ($shop->vendorProfile?->user) {
                $notifier->notifyUser(
                    user: $shop->vendorProfile->user,
                    eventType: $delist ? 'SHOP_DELISTED' : 'SHOP_RESTORED',
                    title: $delist ? "Workshop Delisted: {$shop->name}" : "Workshop Restored: {$shop->name}",
                    message: $reason,
                    actionUrl: '/vendor/dashboard',
                    severity: $delist ? 'danger' : 'success'
                );
            }

            return redirect('/admin/moderation')->with(
                'success',
                $delist ? "{$shop->name} delisted from public marketplace." : "{$shop->name} restored to public marketplace."
            );
        }

        $review = ShopReview::with('shop.vendorProfile')->findOrFail($targetId);
        $hide = $action === 'hide_review';
        $review->update([
            'is_hidden' => $hide,
            'moderated_at' => now(),
            'moderated_by_id' => $admin->id,
            'moderation_note' => $reason,
        ]);
        if ($review->shop) {
            ReviewController::recalculateShopRating($review->shop->fresh(['vendorProfile']));
        }
        ModerationLog::create([
            'admin_id' => $admin->id,
            'target_type' => 'review',
            'target_id' => $review->id,
            'violation_type' => $violation,
            'action_taken' => $hide ? 'review_hidden' : 'review_unhidden',
            'reason' => $reason,
        ]);

        return redirect('/admin/moderation')->with('success', "Review #{$review->id} visibility updated.");
    }

    /**
     * Resolve an Order Dispute Case (with optional full or partial Escrow Refund).
     * Route: POST /admin/disputes/{id}/resolve
     */
    public function resolveDispute(Request $request, $id, MultiChannelNotificationService $notifier)
    {
        $dispute = DisputeCase::with(['repairRequest', 'openedBy', 'against', 'payment'])->findOrFail($id);

        $validated = $request->validate([
            'decision' => 'required|in:resolve,reject,full_refund,partial_refund',
            'resolution' => 'required|string|min:5|max:1000',
            'refund_amount' => 'nullable|numeric|min:1',
        ]);

        $decision = $validated['decision'];
        $resolutionText = trim($validated['resolution']);
        $payment = $dispute->payment
            ?? ($dispute->repair_request_id
                ? PaymentTransaction::where('repair_request_id', $dispute->repair_request_id)->where('status', 'PAID')->latest('id')->first()
                : null);

        DB::transaction(function () use ($dispute, $decision, $resolutionText, $validated, $payment, $request) {
            $newStatus = match ($decision) {
                'reject' => 'REJECTED',
                'full_refund' => 'REFUNDED',
                'partial_refund' => 'PARTIALLY_REFUNDED',
                default => 'RESOLVED',
            };

            if (in_array($decision, ['full_refund', 'partial_refund'], true) && $payment && $payment->status !== 'REFUNDED') {
                $paidAmount = (float) $payment->amount;
                $refundAmt = $decision === 'full_refund'
                    ? $paidAmount
                    : min($paidAmount, round((float) ($validated['refund_amount'] ?? ($paidAmount * 0.5)), 2));

                $isFull = $refundAmt >= round($paidAmount - 0.01, 2);
                $shopId = $payment->invoice_id
                    ? DB::table('invoices')->where('id', $payment->invoice_id)->value('shop_id')
                    : DB::table('financial_ledgers')->where('payment_id', $payment->id)->whereNotNull('shop_id')->value('shop_id');

                DB::table('refunds')->insert([
                    'payment_id' => $payment->id,
                    'dispute_case_id' => $dispute->id,
                    'approved_by_id' => auth()->id(),
                    'amount' => $refundAmt,
                    'reason' => $resolutionText,
                    'status' => 'PROCESSED',
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $payment->update([
                    'status' => $isFull ? 'REFUNDED' : 'PARTIALLY_REFUNDED',
                ]);

                if ($payment->invoice_id && $isFull) {
                    DB::table('invoices')->where('id', $payment->invoice_id)->update([
                        'status' => 'REFUNDED',
                        'updated_at' => now(),
                    ]);
                }

                $split = AdminLedgerController::computeRefundSplit($payment, $refundAmt, $shopId ? (int) $shopId : null);

                DB::table('escrow_ledgers')->insert([
                    'payment_id' => $payment->id,
                    'repair_request_id' => $dispute->repair_request_id,
                    'customer_user_id' => $payment->user_id,
                    'vendor_user_id' => $dispute->against_id,
                    'amount' => $refundAmt,
                    'gross_amount' => $refundAmt,
                    'platform_commission_amount' => $split['commission_reversal'],
                    'vendor_net_amount' => $split['vendor_clawback'],
                    'action' => 'REFUND',
                    'note' => "Dispute #{$dispute->id} arbitration refund: {$resolutionText}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                AdminLedgerController::logEntry(
                    'REFUND',
                    'DEBIT',
                    $refundAmt,
                    "Dispute #{$dispute->id} escrow refund ({$payment->transaction_reference}) — {$resolutionText}",
                    [
                        'shop_id' => $shopId,
                        'payment_id' => $payment->id,
                        'invoice_id' => $payment->invoice_id,
                    ]
                );

                if ($split['commission_reversal'] > 0.009) {
                    AdminLedgerController::logEntry(
                        'PLATFORM_COMMISSION',
                        'DEBIT',
                        $split['commission_reversal'],
                        "Platform commission reversal for refunded Dispute #{$dispute->id} ({$payment->transaction_reference})",
                        [
                            'shop_id' => $shopId,
                            'payment_id' => $payment->id,
                            'invoice_id' => $payment->invoice_id,
                        ]
                    );
                }
            }

            $dispute->update([
                'payment_id' => $dispute->payment_id ?? $payment?->id,
                'assigned_admin_id' => auth()->id(),
                'status' => $newStatus,
                'resolution' => $resolutionText,
                'resolved_at' => now(),
            ]);

            DisputeNote::create([
                'dispute_case_id' => $dispute->id,
                'author_id' => auth()->id(),
                'note' => "[Admin Resolution: {$newStatus}] {$resolutionText}",
                'is_internal' => false,
            ]);

            ModerationLog::create([
                'admin_id' => auth()->id(),
                'target_type' => 'dispute',
                'target_id' => $dispute->id,
                'violation_type' => 'order_dispute_arbitration',
                'action_taken' => strtolower($newStatus),
                'reason' => $resolutionText,
            ]);

            AuditLog::create([
                'actor_id' => auth()->id(),
                'action' => 'dispute_resolved',
                'target_resource' => "dispute_cases:{$dispute->id}",
                'payload_after' => ['status' => $newStatus, 'resolution' => $resolutionText],
                'ip_address' => $request->ip() ?? '127.0.0.1',
            ]);
        });

        if ($dispute->openedBy) {
            $notifier->notifyUser(
                user: $dispute->openedBy,
                eventType: 'DISPUTE_RESOLVED',
                title: "Dispute #{$dispute->id} Updated ({$dispute->status})",
                message: $resolutionText,
                actionUrl: '/support',
                repairRequestId: $dispute->repair_request_id,
                severity: 'info'
            );
        }

        return redirect('/admin/moderation')->with('success', "Dispute #{$dispute->id} resolved as {$dispute->status}.");
    }

    /**
     * Resolve an escalated Customer / Vendor Support Ticket.
     * Route: POST /admin/support/{id}/resolve
     */
    public function resolveSupportTicket(Request $request, $id, MultiChannelNotificationService $notifier)
    {
        $ticket = SupportTicket::with('user')->findOrFail($id);
        $validated = $request->validate([
            'admin_notes' => 'required|string|min:3|max:1000',
            'status' => 'nullable|in:IN_PROGRESS,RESOLVED,CLOSED',
        ]);

        $newStatus = $validated['status'] ?? 'RESOLVED';
        $note = trim($validated['admin_notes']);

        $ticket->update([
            'assigned_admin_id' => auth()->id(),
            'status' => $newStatus,
            'admin_notes' => $note,
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => auth()->id(),
            'sender_type' => 'admin',
            'message' => $note,
        ]);

        if ($ticket->user) {
            $notifier->notifyUser(
                user: $ticket->user,
                eventType: 'SUPPORT_TICKET_UPDATED',
                title: "Support Ticket #{$ticket->id} ({$newStatus})",
                message: $note,
                actionUrl: '/support',
                repairRequestId: $ticket->repair_request_id,
                severity: 'success'
            );
        }

        return redirect('/admin/moderation')->with('success', "Support Ticket #{$ticket->id} marked {$newStatus}.");
    }

    /**
     * Unified Customer, Vendor & Rider Support & Escrow Dispute Desk.
     * Accessible to guests and all roles (HTTP 200).
     * Route: GET /support & GET /help
     */
    public function supportDesk(Request $request)
    {
        $user = $request->user();
        $preselectedOrderId = $request->query('order_id');

        $myOrders = collect();
        $myDisputes = collect();
        $myTickets = collect();

        if ($user) {
            if ($user->role === 'admin') {
                $myOrders = RepairRequest::latest()->take(25)->get();
                $myDisputes = DisputeCase::with(['repairRequest', 'notes.author', 'refunds'])->latest()->take(15)->get();
                $myTickets = SupportTicket::with(['messages.author', 'repairRequest'])->latest()->take(15)->get();
            } else {
                $myOrders = RepairRequest::where('customer_id', $user->id)->latest()->take(25)->get();
                $myDisputes = DisputeCase::with(['repairRequest', 'notes.author', 'refunds'])
                    ->where(fn ($q) => $q->where('opened_by_id', $user->id)->orWhere('against_id', $user->id))
                    ->latest()
                    ->get();
                $myTickets = SupportTicket::with(['messages.author', 'repairRequest'])
                    ->where('user_id', $user->id)
                    ->latest()
                    ->get();
            }
        }

        return view('support.index', compact('myOrders', 'myDisputes', 'myTickets', 'preselectedOrderId'));
    }

    /**
     * File a new Order / Escrow Dispute Case.
     * Route: POST /disputes
     */
    public function storeDispute(Request $request, MultiChannelNotificationService $notifier)
    {
        $user = $request->user();
        if (!$user) {
            return redirect('/login');
        }

        $validated = $request->validate([
            'repair_request_id' => 'required|integer|exists:repair_requests,id',
            'reason' => 'required|string|min:10|max:1000',
        ]);

        $req = RepairRequest::with(['repairJob.shop.vendorProfile', 'requestedShop.vendorProfile'])->findOrFail($validated['repair_request_id']);
        if ((int) $req->customer_id !== (int) $user->id && $user->role !== 'admin') {
            abort(403, 'Only the customer who placed this order can file an order dispute.');
        }

        $againstId = $req->repairJob?->shop?->vendorProfile?->user_id
            ?? $req->requestedShop?->vendorProfile?->user_id
            ?? User::where('role', 'admin')->value('id')
            ?? $user->id;

        $payment = PaymentTransaction::where('repair_request_id', $req->id)
            ->orderByRaw("CASE WHEN status = 'PAID' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();

        $dispute = DB::transaction(function () use ($req, $user, $againstId, $payment, $validated) {
            $case = DisputeCase::create([
                'repair_request_id' => $req->id,
                'repair_job_id' => $req->repairJob?->id,
                'payment_id' => $payment?->id,
                'opened_by_id' => $user->id,
                'against_id' => $againstId,
                'status' => 'OPEN',
                'resolution' => null,
            ]);

            DisputeNote::create([
                'dispute_case_id' => $case->id,
                'author_id' => $user->id,
                'note' => trim($validated['reason']),
                'is_internal' => false,
            ]);

            AuditLog::create([
                'actor_id' => $user->id,
                'action' => 'dispute_opened',
                'target_resource' => "dispute_cases:{$case->id}",
                'payload_after' => ['repair_request_id' => $req->id, 'reason' => trim($validated['reason'])],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return $case;
        });

        $againstUser = User::find($againstId);
        if ($againstUser && (int) $againstUser->id !== (int) $user->id) {
            $notifier->notifyUser(
                user: $againstUser,
                eventType: 'DISPUTE_OPENED',
                title: "Order Dispute #{$dispute->id} Opened (Order #{$req->id})",
                message: trim($validated['reason']),
                actionUrl: '/support',
                repairRequestId: $req->id,
                severity: 'warning'
            );
        }

        return redirect('/support')->with('success', "Escrow Dispute Case #{$dispute->id} opened for Order #{$req->id}. Meramot Governance will review and respond promptly.");
    }

    /**
     * Append a customer / vendor / admin note to an active DisputeCase.
     * Route: POST /disputes/{id}/notes
     */
    public function addDisputeNote(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return redirect('/login');
        }

        $dispute = DisputeCase::findOrFail($id);
        if ($user->role !== 'admin' && (int) $dispute->opened_by_id !== (int) $user->id && (int) $dispute->against_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'note' => 'required|string|min:2|max:1000',
        ]);

        DisputeNote::create([
            'dispute_case_id' => $dispute->id,
            'author_id' => $user->id,
            'note' => trim($validated['note']),
            'is_internal' => $user->role === 'admin' && $request->boolean('is_internal'),
        ]);

        return back()->with('success', "Evidence / note added to Dispute #{$dispute->id}.");
    }

    /**
     * Create a new Help & Support Ticket.
     * Route: POST /support/tickets
     */
    public function storeSupportTicket(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect('/login');
        }

        $validated = $request->validate([
            'subject' => 'required|string|min:4|max:180',
            'message' => 'required|string|min:8|max:1500',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'repair_request_id' => 'nullable|integer|exists:repair_requests,id',
        ]);

        $ticket = DB::transaction(function () use ($user, $validated) {
            $t = SupportTicket::create([
                'user_id' => $user->id,
                'repair_request_id' => $validated['repair_request_id'] ?? null,
                'subject' => trim($validated['subject']),
                'message' => trim($validated['message']),
                'status' => 'OPEN',
                'priority' => $validated['priority'] ?? 'normal',
            ]);

            SupportMessage::create([
                'ticket_id' => $t->id,
                'author_id' => $user->id,
                'sender_type' => 'user',
                'message' => trim($validated['message']),
            ]);

            return $t;
        });

        return redirect('/support')->with('success', "Support Ticket #{$ticket->id} submitted! Our support team will reply here and via notification.");
    }

    /**
     * Reply to an existing SupportTicket thread.
     * Route: POST /support/tickets/{id}/reply
     */
    public function replySupportTicket(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return redirect('/login');
        }

        $ticket = SupportTicket::findOrFail($id);
        if ($user->role !== 'admin' && (int) $ticket->user_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'message' => 'required|string|min:2|max:1200',
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $user->id,
            'sender_type' => $user->role === 'admin' ? 'admin' : 'user',
            'message' => trim($validated['message']),
        ]);

        if ($user->role === 'admin' && $ticket->status === 'OPEN') {
            $ticket->update(['status' => 'IN_PROGRESS', 'assigned_admin_id' => $user->id]);
        }

        return back()->with('success', "Reply posted to Support Ticket #{$ticket->id}.");
    }
}
