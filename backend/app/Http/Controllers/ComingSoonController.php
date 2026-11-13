<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * ============================================================================
 * Meramot Roadmap Placeholder & Coming Soon Controller
 * ============================================================================
 * Author: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Purpose:
 *   Provides a clean, intentional "Coming Soon" landing endpoint for features,
 *   buttons, and links scheduled for upcoming sprints (Sprint 2, Sprint 3,
 *   Sprint 4) so that zero links or form actions dead-end during Sprint 1 demo.
 * ============================================================================
 */
class ComingSoonController extends Controller
{
    /**
     * Metadata directory for scheduled Sprint 2–4 features and auxiliary pages.
     */
    private const FEATURES = [
        'sslcommerz-payment' => [
            'title' => 'Online Payment Gateway & Tax Invoices',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Meramot Platform Roadmap',
            'icon' => 'fa-credit-card',
            'summary' => 'Hosted SSLCommerz, bKash, and Nagad online escrow checkout along with downloadable PDF tax invoices will be introduced in an upcoming release. Cash and Escrow on Pickup & Direct Orders remain active.',
        ],
        'rider-dispatch-queue' => [
            'title' => 'Live Rider Job Claiming & OTP Handover',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Meramot Platform Roadmap',
            'icon' => 'fa-motorcycle',
            'summary' => 'Real-time courier job claiming and 4-digit OTP pickup and delivery verification milestones are scheduled for a future platform update. Rider onboarding and credential verification remain active.',
        ],
        'rider-earnings-payout' => [
            'title' => 'Rider Earnings Wallet & Mobile Banking Payouts',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Meramot Platform Roadmap',
            'icon' => 'fa-wallet',
            'summary' => 'Automated trip-by-trip courier earnings ledgers and mobile banking withdrawal requests are planned for a future release.',
        ],
        'live-rider-gps' => [
            'title' => 'Real-Time Courier GPS Map & Route Sharing',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Meramot Platform Roadmap',
            'icon' => 'fa-satellite-dish',
            'summary' => 'Live courier route telemetry between your pickup address and verified repair workshops is scheduled for an upcoming release.',
        ],
        'rider-live-chat' => [
            'title' => 'Real-Time Support & Courier Messaging',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Meramot Platform Roadmap',
            'icon' => 'fa-comments',
            'summary' => 'Direct real-time messaging between customers, couriers, and Meramot operations support will launch in an upcoming release.',
        ],
        'ai-request-triage' => [
            'title' => 'Automated Technician Fault Triage',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Meramot Platform Roadmap',
            'icon' => 'fa-microchip',
            'summary' => 'Automated diagnostic component extraction for workshop workbenches is scheduled for a future platform update.',
        ],
        'multi-channel-alerts' => [
            'title' => 'Multi-Channel SMS & Email Alert Engine',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Meramot Platform Roadmap',
            'icon' => 'fa-bell',
            'summary' => 'Automated SMS dispatch and instant multi-channel order notifications are scheduled for an upcoming platform update.',
        ],
        'vendor-payouts' => [
            'title' => 'Automated Vendor Net Payout Disbursement',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Meramot Platform Roadmap',
            'icon' => 'fa-money-bill-transfer',
            'summary' => 'Automated vendor withdrawal settlement is scheduled for a future update. Double-entry 10% commission and 90% workshop payout accounting is active in the Admin Financial Overview.',
        ],
        'content-moderation' => [
            'title' => 'Automated Review Flagging & Governance',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Meramot Platform Roadmap',
            'icon' => 'fa-gavel',
            'summary' => 'Expanded automated review screening and trust-and-safety workflows are planned for a future release.',
        ],
        'security-audit-logs' => [
            'title' => 'Security & Compliance Audit Monitor',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Meramot Platform Roadmap',
            'icon' => 'fa-shield-halved',
            'summary' => 'Advanced security anomaly detection and state change auditing are planned for an upcoming release.',
        ],
        'password-recovery' => [
            'title' => 'Self-Service SMS Password Recovery',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Account Security Roadmap',
            'icon' => 'fa-key',
            'summary' => 'SMS OTP password reset links will expand alongside our upcoming notification gateway. Email OTP password reset is available now from the Sign In page.',
        ],
        'user-profile' => [
            'title' => 'Customer Profile & Saved Device Garage',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Customer Account Preferences',
            'icon' => 'fa-id-card',
            'summary' => 'Extended hardware serial management and archived invoice history are planned for an upcoming update. Active repair requests remain accessible in My Repair Requests.',
        ],
        'support-desk' => [
            'title' => 'Escrow Dispute & Live Support Center',
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Meramot Operations Support',
            'icon' => 'fa-headset',
            'summary' => 'Integrated dispute arbitration and live support case management are scheduled for an upcoming release.',
        ],
    ];

    /**
     * Render the Mint-styled Coming Soon page for a specific feature key.
     */
    public function show(Request $request, ?string $feature = null)
    {
        $slug = strtolower(trim((string) ($feature ?: $request->query('feature', ''))));
        $meta = self::FEATURES[$slug] ?? [
            'title' => $request->query('title')
                ? (string) $request->query('title')
                : ($slug !== '' ? ucwords(str_replace(['-', '_'], ' ', $slug)) : 'Upcoming Meramot Feature'),
            'sprint' => 'Upcoming Release',
            'window' => 'Planned Platform Rollout',
            'code' => 'Meramot Platform Roadmap',
            'icon' => 'fa-hourglass-half',
            'summary' => 'This capability is scheduled for an upcoming platform release and is currently under active development.',
        ];

        // Normalize accidental space in icon name if any
        $meta['icon'] = str_replace(' ', '', $meta['icon']);

        $relatedRequestId = $request->input('repair_request_id')
            ?? $request->query('request_id')
            ?? null;

        return view('coming-soon', [
            'featureSlug' => $slug ?: 'upcoming',
            'meta' => $meta,
            'relatedRequestId' => $relatedRequestId,
            'wasFormSubmission' => !$request->isMethod('GET'),
        ]);
    }

    /**
     * Catch-all fallback for any unrouted web link so no click ever hits a raw error page.
     */
    public function fallback(Request $request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'coming_soon',
                'message' => 'This endpoint is scheduled for an upcoming release.',
            ], 404);
        }

        $pathLabel = trim($request->path(), '/');
        $prettyTitle = $pathLabel !== ''
            ? ucwords(str_replace(['/', '-', '_'], ' ', $pathLabel))
            : 'Upcoming Platform Module';

        return view('coming-soon', [
            'featureSlug' => $pathLabel ?: 'upcoming',
            'meta' => [
                'title' => $prettyTitle,
                'sprint' => 'Upcoming Release',
                'window' => 'Planned Platform Rollout',
                'code' => 'Meramot Platform Roadmap',
                'icon' => 'fa-compass-drafting',
                'summary' => 'This module is currently scheduled on the Meramot roadmap and will be enabled in an upcoming release.',
            ],
            'relatedRequestId' => null,
            'wasFormSubmission' => !$request->isMethod('GET'),
        ]);
    }
}
