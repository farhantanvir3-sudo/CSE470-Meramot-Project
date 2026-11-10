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
            'title' => 'SSLCommerz Online Payment Gateway & PDF Invoice',
            'sprint' => 'Sprint 2',
            'window' => 'Nov 13 – Nov 27, 2026',
            'code' => 'Feature R17 • Md. Nawshad Zaman Khan [23301622]',
            'icon' => 'fa-credit-card',
            'summary' => 'Hosted SSLCommerz / bKash / Nagad online escrow checkout and automated downloadable PDF tax invoices are scheduled for Sprint 2. Cash / Escrow on Pickup & Direct Orders are fully active right now.',
        ],
        'rider-dispatch-queue' => [
            'title' => 'Live Rider Job Claiming & OTP Milestone Sync',
            'sprint' => 'Sprint 2',
            'window' => 'Nov 13 – Nov 27, 2026',
            'code' => 'Feature R2 • Md. Nafiul Islam [22299035]',
            'icon' => 'fa-motorcycle',
            'summary' => 'Real-time rider job claiming and 4-digit OTP pickup/delivery handover milestones are scheduled for Sprint 2. Rider onboarding and admin credential verification (R1) are live in Sprint 1.',
        ],
        'rider-earnings-payout' => [
            'title' => 'Rider Earnings Wallet & bKash Payout Withdrawal',
            'sprint' => 'Sprint 3',
            'window' => 'Nov 28 – Dec 9, 2026',
            'code' => 'Feature R3 • Md. Nafiul Islam [22299035]',
            'icon' => 'fa-wallet',
            'summary' => 'Automated trip-by-trip courier wallet ledger and instant mobile banking payout withdrawals will roll out in Sprint 3.',
        ],
        'live-rider-gps' => [
            'title' => 'Real-Time Courier GPS Map & Route Sharing',
            'sprint' => 'Sprint 3',
            'window' => 'Nov 28 – Dec 9, 2026',
            'code' => 'Feature R4 • Md. Nafiul Islam [22299035]',
            'icon' => 'fa- satellite-dish',
            'summary' => 'Live Leaflet courier telemetry streaming between customer doorstep and verified repair workshops is scheduled for Sprint 3.',
        ],
        'rider-live-chat' => [
            'title' => 'Real-Time Support & Courier Live Chat Desk',
            'sprint' => 'Sprint 4',
            'window' => 'Dec 10 – Dec 20, 2026',
            'code' => 'Feature R5 • Md. Nafiul Islam [22299035]',
            'icon' => 'fa-comments',
            'summary' => 'WebSocket / Pusher live support messaging between customers, riders, and the Meramot operations desk is scheduled for Sprint 4.',
        ],
        'ai-request-triage' => [
            'title' => 'FastAPI Automated Technician Fault Triage',
            'sprint' => 'Sprint 2',
            'window' => 'Nov 13 – Nov 27, 2026',
            'code' => 'Feature R13 • Farhan Tanvir [22299179]',
            'icon' => 'fa-microchip',
            'summary' => 'Automated GROQ / Gemini motherboard fault-point extraction for technician workbenches is scheduled for Sprint 2.',
        ],
        'multi-channel-alerts' => [
            'title' => 'Multi-Channel BulkSMS BD & Email Alert Engine',
            'sprint' => 'Sprint 3',
            'window' => 'Nov 28 – Dec 9, 2026',
            'code' => 'Feature R14 • Farhan Tanvir [22299179]',
            'icon' => 'fa-bell',
            'summary' => 'Automated SMS dispatch via BulkSMS BD and instant email bid alerts are scheduled for Sprint 3.',
        ],
        'vendor-payouts' => [
            'title' => 'Automated Vendor Net Payout Disbursement',
            'sprint' => 'Sprint 3',
            'window' => 'Nov 28 – Dec 9, 2026',
            'code' => 'Feature R18 • Md. Nawshad Zaman Khan [23301622]',
            'icon' => 'fa-money-bill-transfer',
            'summary' => 'Automated vendor withdrawal disbursement is scheduled for Sprint 3. Double-entry 10%/90% ledger settlement is live now in the Admin Financial Overview.',
        ],
        'content-moderation' => [
            'title' => 'User Moderation & Content Governance Sanctions',
            'sprint' => 'Sprint 3',
            'window' => 'Nov 28 – Dec 9, 2026',
            'code' => 'Feature R19 • Md. Nawshad Zaman Khan [23301622]',
            'icon' => 'fa-gavel',
            'summary' => 'Automated review flagging, account warnings, and sanction enforcement workflows are scheduled for Sprint 3.',
        ],
        'security-audit-logs' => [
            'title' => 'Immutable Security & Compliance Audit Monitor',
            'sprint' => 'Sprint 4',
            'window' => 'Dec 10 – Dec 20, 2026',
            'code' => 'Feature R20 • Md. Nawshad Zaman Khan [23301622]',
            'icon' => 'fa-shield-halved',
            'summary' => 'Real-time security anomaly detection, IP forensics, and before/after state diffing are scheduled for Sprint 4.',
        ],
        'password-recovery' => [
            'title' => 'Self-Service Password Recovery & OTP Reset',
            'sprint' => 'Sprint 3',
            'window' => 'Nov 28 – Dec 9, 2026',
            'code' => 'Auth & Notification Integration',
            'icon' => 'fa-key',
            'summary' => 'Email and SMS OTP password reset links will launch alongside the Sprint 3 multi-channel notification gateway. Use the 1-Click Demo Login chips on the Sign In page during Sprint 1 review.',
        ],
        'user-profile' => [
            'title' => 'Customer Profile & Saved Gadget Garage',
            'sprint' => 'Sprint 2',
            'window' => 'Nov 13 – Nov 27, 2026',
            'code' => 'Customer Account Preferences',
            'icon' => 'fa-id-card',
            'summary' => 'Saved hardware serials, default pickup addresses, and invoice history are scheduled for Sprint 2. You can track all active repair requests right now in My Repair Requests.',
        ],
        'support-desk' => [
            'title' => 'Escrow Dispute & Live Support Center',
            'sprint' => 'Sprint 4',
            'window' => 'Dec 10 – Dec 20, 2026',
            'code' => 'Feature R5 • Operations Support',
            'icon' => 'fa-headset',
            'summary' => 'Live dispute arbitration and courier support chat are scheduled for Sprint 4.',
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
            'sprint' => 'Upcoming Sprint',
            'window' => 'Scheduled in Roadmap',
            'code' => 'Meramot Multi-Sprint Roadmap',
            'icon' => 'fa-hourglass-half',
            'summary' => 'This capability is part of a later sprint milestone and is currently under active engineering.',
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
                'message' => 'This API endpoint is scheduled for an upcoming sprint.',
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
                'sprint' => 'Upcoming Sprint',
                'window' => 'Scheduled in Roadmap',
                'code' => 'Meramot Multi-Sprint Roadmap',
                'icon' => 'fa-compass-drafting',
                'summary' => 'This page or action belongs to an upcoming sprint module and will be enabled in its scheduled release.',
            ],
            'relatedRequestId' => null,
            'wasFormSubmission' => !$request->isMethod('GET'),
        ]);
    }
}
