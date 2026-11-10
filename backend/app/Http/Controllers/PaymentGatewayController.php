<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\RepairRequest;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R17: SSLCommerz/Stripe Online Payment Gateway & PDF Invoice
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
 * Architectural Layer: Controller (PaymentGatewayController)
 * Models Touched: PaymentTransaction, Invoice
 * View Rendered: resources/views/checkout/payment.blade.php
 *
 * INSTRUCTIONS FOR NAWSHAD:
 * 1. Implement initiatePayment($requestId): Generate unique transaction ID,
 *    calculate total bill including 10% platform fee, and dispatch SSLCommerz request.
 * 2. Implement handleCallback(Request $request): Validate SSLCommerz IPN hash,
 *    mark payment status as 'successful', and generate customer PDF invoice.
 * ============================================================================
 */
class PaymentGatewayController extends Controller
{
    /**
     * Show payment checkout screen.
     * Route: GET /checkout/payment/{requestId}
     */
    public function showCheckout(Request $request, $requestId)
    {
        $repairRequest = RepairRequest::with(['bids', 'requestedShop'])->find($requestId);
        if (!$repairRequest) {
            return app(ComingSoonController::class)->show($request, 'sslcommerz-payment');
        }

        return view('checkout.payment', compact('repairRequest'));
    }

    /**
     * Initiate payment transaction with SSLCommerz sandbox (Sprint 2 — routes to Coming Soon during Sprint 1).
     * Route: POST /payment/initiate
     */
    public function initiatePayment(Request $request)
    {
        return app(ComingSoonController::class)->show($request, 'sslcommerz-payment');
    }

    /**
     * SSLCommerz success / IPN callback handler (Sprint 2).
     * Route: POST /payment/success
     */
    public function handleSuccess(Request $request)
    {
        return app(ComingSoonController::class)->show($request, 'sslcommerz-payment');
    }
}
