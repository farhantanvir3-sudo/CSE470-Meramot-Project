<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Import all single-CRUD & marketplace controllers for R1 to R20
use App\Http\Controllers\AdminDashboardController;     // Unified Admin Console Hub — Farhan
use App\Http\Controllers\AdminDeliveryController;      // R1 - Nafiul
use App\Http\Controllers\DeliveryPortalController;     // R1/R2/R3/R4 Delivery Partner Portal & Company Auth
use App\Http\Controllers\DeliveryJobController;        // R2 - Nafiul
use App\Http\Controllers\RiderLedgerController;        // R3 - Nafiul
use App\Http\Controllers\TrackingController;           // R4 - Nafiul
use App\Http\Controllers\RiderChatController;          // R5 - Nafiul

use App\Http\Controllers\ShopDiscoveryController;      // R6 - Shanid
use App\Http\Controllers\RepairRequestController;      // R7 - Shanid
use App\Http\Controllers\StatusTransitionController;   // R7 - Dual-Quote Lifecycle
use App\Http\Controllers\ServicePackageController;     // R8 - Shanid + Direct Order Cart
use App\Http\Controllers\ReviewController;             // R9 - Shanid
use App\Http\Controllers\AIChatController;             // R10 - Shanid

use App\Http\Controllers\VendorOnboardingController;   // R11 - Farhan
use App\Http\Controllers\VendorBranchController;       // R11/R12/R15 Multi-Branch, Bidding & Workbench - Farhan
use App\Http\Controllers\BiddingEngineController;      // R12 - Farhan
use App\Http\Controllers\AISummaryController;          // R13 - Farhan
use App\Http\Controllers\VendorNotificationController; // R14 - Farhan
use App\Http\Controllers\VendorAnalyticsController;    // R15 - Farhan

use App\Http\Controllers\AdminLedgerController;        // R16 - Nawshad
use App\Http\Controllers\PaymentGatewayController;     // R17 - Nawshad
use App\Http\Controllers\VendorPayoutController;       // R18 - Nawshad
use App\Http\Controllers\AdminModerationController;    // R19 - Nawshad
use App\Http\Controllers\AuditLogController;           // R20 - Nawshad
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\FrontendAssetController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| Static Asset Bridge from Root `../frontend/` Directory
|--------------------------------------------------------------------------
*/
Route::get('/js/{filename}', [FrontendAssetController::class, 'js'])->where('filename', '[A-Za-z0-9._-]+');
Route::get('/styles/{filename}', [FrontendAssetController::class, 'style'])->where('filename', '[A-Za-z0-9._-]+');

/*
|--------------------------------------------------------------------------
| Web Routes — Meramot Digital Marketplace (CSE 470 Group 6)
|--------------------------------------------------------------------------
*/

Route::get('/healthz', fn () => response()->json(['status' => 'ok', 'service' => 'Meramot Laravel 10 MVC'], 200));

Route::get('/', [ShopDiscoveryController::class, 'home'])->name('home');

// -------------------------------------------------------------
// Authentication Routes (Ported from AuthCard.tsx at commit 8e44218)
// -------------------------------------------------------------
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/auth/demo', [AuthController::class, 'demoLogin'])->name('auth.demo');
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::get('/verify-email-otp', [AuthController::class, 'showVerifyOtp'])->name('auth.verify-otp.form');
Route::post('/verify-email-otp', [AuthController::class, 'verifyOtp'])->name('auth.verify-otp.submit');
Route::post('/verify-email-otp/send', [AuthController::class, 'sendVerificationOtp'])->name('auth.verify-otp.send');
Route::post('/api/verification/send-otp', [AuthController::class, 'sendVerificationOtp']);
Route::post('/api/verification/verify-otp', [AuthController::class, 'verifyOtp']);
Route::get('/forgot-password', fn (Request $r) => app(ComingSoonController::class)->show($r, 'password-recovery'))->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendForgotPasswordEmail'])->name('password.email');

// -------------------------------------------------------------
// MODULE: Delivery & Rider Logistics (Assigned: Md. Nafiul Islam [22299035])
// -------------------------------------------------------------
Route::prefix('delivery')->group(function () {
    Route::get('/login', [DeliveryPortalController::class, 'showLogin'])->name('delivery.login');       // R1 Company Login
    Route::post('/login', [DeliveryPortalController::class, 'login'])->name('delivery.login.submit');   // R1 Company Login
    Route::post('/logout', [DeliveryPortalController::class, 'logout'])->name('delivery.logout');       // R1 Sign Out
    Route::get('/', [DeliveryPortalController::class, 'dashboard'])->name('delivery.dashboard');        // R1/R2 Rider Dashboard
    Route::get('/map', [DeliveryPortalController::class, 'map'])->name('delivery.map');                 // R4 Live Rider Map
    Route::post('/location', [DeliveryPortalController::class, 'updateLocation'])->name('delivery.location'); // R4 GPS Sync
    Route::get('/order/{id}', [DeliveryPortalController::class, 'orderDetails'])->name('delivery.order.show'); // R2 Order Details
    Route::post('/order/{id}/status', [DeliveryPortalController::class, 'advanceOrderStatus'])->name('delivery.order.status'); // R2 Milestone
    Route::get('/profile', [DeliveryPortalController::class, 'profile'])->name('delivery.profile');     // R1 Rider Profile

    Route::get('/jobs-queue', [DeliveryJobController::class, 'index'])->name('delivery.jobs-queue');   // R2
    Route::post('/jobs/{id}/claim', [DeliveryJobController::class, 'claimJob']);                       // R2
    Route::post('/jobs/{id}/milestone', [DeliveryJobController::class, 'updateMilestone']);            // R2
    Route::get('/earnings', [RiderLedgerController::class, 'index'])->name('delivery.earnings');       // R3
    Route::post('/payout/request', [RiderLedgerController::class, 'requestPayout']);                   // R3
});
// Feature R1 Routes (Added by Md. Nafiul Islam [22299035] in c6c0781 & fdf5e6f + Renewed Onboarding & OTP Status Flow)
Route::middleware('admin.access')->group(function () {
    Route::get('/admin/delivery-agents/pending', [AdminDeliveryController::class, 'index'])->name('admin.delivery-agents.pending');   // R1
    Route::post('/admin/delivery-agents/{id}/approve', [AdminDeliveryController::class, 'approve'])->name('admin.delivery-agents.approve'); // R1
    Route::post('/admin/delivery-agents/{id}/reject', [AdminDeliveryController::class, 'reject'])->name('admin.delivery-agents.reject');     // R1
    Route::post('/admin/delivery-agents/{id}/block', [AdminDeliveryController::class, 'block'])->name('admin.delivery-agents.block');         // R1
    Route::delete('/admin/delivery-agents/{id}', [AdminDeliveryController::class, 'destroy'])->name('admin.delivery-agents.destroy');         // R1 Delete
    Route::get('/admin/delivery-agents/{id}/document/{type}', [AdminDeliveryController::class, 'viewDocument'])->name('admin.delivery-agents.document'); // R1 Doc Preview
});

Route::get('/delivery-agent/register', [AdminDeliveryController::class, 'create'])->name('delivery.register');                            // R1
Route::post('/delivery-agent/register', [AdminDeliveryController::class, 'store'])->name('delivery.register.store');                      // R1
Route::get('/delivery-agent/status', [AdminDeliveryController::class, 'showStatus'])->name('delivery.status');                            // R1 OTP Status
Route::post('/delivery-agent/status/send-otp', [AdminDeliveryController::class, 'sendStatusOtp'])->name('delivery.status.send-otp');      // R1
Route::post('/delivery-agent/status/verify-otp', [AdminDeliveryController::class, 'verifyStatusOtp'])->name('delivery.status.verify-otp'); // R1
Route::post('/delivery-agent/status/reset', [AdminDeliveryController::class, 'resetStatusCheck'])->name('delivery.status.reset');          // R1
Route::post('/delivery-agent/status/resend-credentials', [AdminDeliveryController::class, 'resendCompanyCredentials'])->name('delivery.status.resend-credentials'); // R1 Forgot Credentials
Route::get('/delivery-agent/edit', [AdminDeliveryController::class, 'editApplication'])->name('delivery.edit');                           // R1 Unlimited Edit
Route::post('/delivery-agent/edit', [AdminDeliveryController::class, 'updateApplication'])->name('delivery.edit.update');                 // R1
Route::get('/tracking/{jobId}', [TrackingController::class, 'showMap'])->name('tracking.show');        // R4
Route::get('/support/rider-chat/{riderId}', [RiderChatController::class, 'index']);                    // R5
Route::post('/support/rider-chat/send', [RiderChatController::class, 'sendMessage']);                  // R5
Route::post('/api/support/rider-chat/send', [RiderChatController::class, 'sendMessage']);              // R5

// -------------------------------------------------------------
// MODULE: Discovery, Requests & AI Consultation (Assigned: Shanid Sajjatuz Islam)
// -------------------------------------------------------------
Route::get('/shops', [ShopDiscoveryController::class, 'index'])->name('shops.index');
Route::get('/shops/explore', [ShopDiscoveryController::class, 'index'])->name('shops.explore');
Route::get('/shops/{slug}', [ShopDiscoveryController::class, 'show'])->name('shops.show'); // R6

Route::get('/requests', [RepairRequestController::class, 'index'])->name('requests.index');            // R7 Customer Hub
Route::get('/orders', [RepairRequestController::class, 'index'])->name('orders.index');                // R7 Alias
Route::get('/requests/create', [RepairRequestController::class, 'create'])->name('requests.create');   // R7
Route::post('/requests', [RepairRequestController::class, 'store'])->name('requests.store');           // R7
Route::get('/requests/{id}/track', [RepairRequestController::class, 'track'])->name('requests.track'); // R7
Route::post('/requests/{id}/bids', [VendorBranchController::class, 'submitBid']);                      // R7/R12 Web Bid Alias
Route::post('/requests/{id}/update-status', [StatusTransitionController::class, 'updateStatus']);      // R7
Route::get('/requests/{id}/available-transitions', [StatusTransitionController::class, 'getAvailableTransitions']); // R7
Route::post('/requests/{id}/accept-bid', [StatusTransitionController::class, 'acceptBid'])->name('requests.accept-bid'); // R7
Route::post('/requests/{id}/respond-final-quote', [StatusTransitionController::class, 'respondToFinalQuote'])->name('requests.respond-final-quote'); // R7 Quote 2
Route::post('/requests/{id}/cancel', [StatusTransitionController::class, 'cancel'])->name('requests.cancel'); // R7

Route::get('/packages/catalog', [ServicePackageController::class, 'index'])->name('packages.catalog'); // R8
Route::post('/cart/add', [ServicePackageController::class, 'addToCart'])->name('cart.add');            // R8
Route::post('/cart/shop', [ServicePackageController::class, 'selectCartShop'])->name('cart.shop');     // R8 Quickmatch shop
Route::get('/cart', [ServicePackageController::class, 'viewCart'])->name('cart.view');                 // R8
Route::delete('/cart/remove/{packageId}', [ServicePackageController::class, 'removeFromCart'])->name('cart.remove'); // R8
Route::patch('/cart/update/{packageId}', [ServicePackageController::class, 'updateCartQuantity'])->name('cart.update'); // R8
Route::post('/cart/clear', [ServicePackageController::class, 'clearCart'])->name('cart.clear');        // R8
Route::get('/cart/checkout', [ServicePackageController::class, 'checkout'])->name('cart.checkout');    // R8
Route::post('/cart/checkout/place', [ServicePackageController::class, 'placeDirectOrder'])->name('cart.place'); // R8 Direct Order

Route::get('/reviews/create/{shopId}', [ReviewController::class, 'create'])->name('reviews.create');   // R9
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');                    // R9
Route::get('/ai-consultant/chat', [AIChatController::class, 'index'])->name('ai-consultant.chat');     // R10

// -------------------------------------------------------------
// MODULE: Vendor Onboarding, Bidding & Analytics (Assigned: Farhan Tanvir - Lead)
// -------------------------------------------------------------
Route::get('/api/vendors/check-email', [VendorOnboardingController::class, 'checkEmail'])->name('vendor.check-email');
Route::prefix('vendor')->group(function () {
    Route::get('/apply', [VendorOnboardingController::class, 'showApply'])->name('vendor.apply');                   // R11 Dedicated Vendor Sign-Up
    Route::post('/apply', [VendorOnboardingController::class, 'storeApply'])->name('vendor.apply.store');           // R11
    Route::get('/apply/success', [VendorOnboardingController::class, 'showApplySuccess'])->name('vendor.apply.success'); // R11
    Route::get('/status', [VendorOnboardingController::class, 'showStatus'])->name('vendor.status');                // R11
    Route::get('/setup-shop', [VendorOnboardingController::class, 'showSetupShop'])->name('vendor.setup-shop');     // R11 Post-Approval Shop Setup
    Route::post('/setup-shop', [VendorOnboardingController::class, 'completeSetupShop'])->name('vendor.setup-shop.store'); // R11
    Route::get('/onboarding', [VendorOnboardingController::class, 'show'])->name('vendor.onboarding');             // R11
    Route::post('/onboarding', [VendorOnboardingController::class, 'store'])->name('vendor.onboarding.store');     // R11
    Route::get('/bidding-board', [BiddingEngineController::class, 'index'])->name('vendor.bidding-board');         // R12
    Route::get('/request-details/{id}', function ($id) { return view('vendor.request-details'); });                // R13
    Route::get('/alert-settings', function () { return view('vendor.alert-settings'); });                          // R14
    Route::get('/dashboard', [VendorBranchController::class, 'dashboard'])->name('vendor.dashboard');              // R15 + Multi-Branch
    Route::post('/branches/toggle', [VendorBranchController::class, 'enableBranching'])->name('vendor.branches.toggle');
    Route::post('/branches', [VendorBranchController::class, 'storeBranch'])->name('vendor.branches.store');
    Route::patch('/branches/{shopId}', [VendorBranchController::class, 'updateBranchAddresses'])->name('vendor.branches.update');
    Route::post('/direct-orders/{id}/respond', [StatusTransitionController::class, 'respondToDirectOrder'])->name('vendor.direct-orders.respond');
    Route::post('/requests/{id}/bid', [VendorBranchController::class, 'submitBid'])->name('vendor.requests.bid');  // R12 Real Web Bid
    Route::post('/jobs/{jobId}/status', [VendorBranchController::class, 'updateJobStatus'])->name('vendor.jobs.status'); // Bench Status + R16 Settlement
});

// -------------------------------------------------------------
// MODULE: Admin Hub, Vendor Verification (R11) & Finance/Governance (Nawshad [23301622])
// -------------------------------------------------------------
Route::prefix('admin')->middleware('admin.access')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    // Feature R11: Admin Vendor Verification Desk (Farhan Tanvir [22299179])
    Route::get('/vendors/pending', [VendorOnboardingController::class, 'adminIndex'])->name('admin.vendors.pending');
    Route::post('/vendors/{id}/approve', [VendorOnboardingController::class, 'approve'])->name('admin.vendors.approve');
    Route::post('/vendors/{id}/reject', [VendorOnboardingController::class, 'reject'])->name('admin.vendors.reject');
    Route::post('/vendors/{id}/status', [VendorOnboardingController::class, 'updateStatus'])->name('admin.vendors.status');
    Route::delete('/vendors/{id}', [VendorOnboardingController::class, 'destroy'])->name('admin.vendors.destroy');
    Route::post('/vendors/shops/{shopId}/toggle-featured', [VendorOnboardingController::class, 'toggleFeatured'])->name('admin.vendors.toggle-featured');
    Route::get('/vendors/{id}/document/{type}', [VendorOnboardingController::class, 'viewDocument'])->name('admin.vendors.document');

    // Feature R16: Platform Financial Summary & Double-Entry Ledger (Md. Nawshad Zaman Khan [23301622] — Commit 4742c9c)
    Route::get('/financial-overview', [AdminLedgerController::class, 'index'])->name('admin.financial-overview');         // R16
    Route::post('/financial-overview/settle', [AdminLedgerController::class, 'settleManual'])->name('admin.financial-overview.settle'); // R16
    Route::post('/financial-overview/auto-settle', [AdminLedgerController::class, 'autoSettleBatch'])->name('admin.financial-overview.auto-settle'); // R16
    Route::post('/financial-overview/bulk-delete', [AdminLedgerController::class, 'bulkDelete'])->name('admin.financial-overview.bulk-delete'); // R16

    Route::get('/payouts/manage', [VendorPayoutController::class, 'index'])->name('admin.payouts.manage');                // R18
    Route::post('/payouts/{id}/disburse', [VendorPayoutController::class, 'disbursePayout'])->name('admin.payouts.disburse'); // R18
    Route::get('/moderation', [AdminModerationController::class, 'index'])->name('admin.moderation');                     // R19
    Route::post('/moderation/sanction', [AdminModerationController::class, 'applySanction'])->name('admin.moderation.sanction'); // R19
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs');                            // R20
});

// -------------------------------------------------------------
// Payment Checkout (R17 — Sprint 2) & Roadmap Coming Soon Endpoints
// -------------------------------------------------------------
Route::get('/checkout/payment/{requestId}', [PaymentGatewayController::class, 'showCheckout'])->name('checkout.payment'); // R17
Route::post('/payment/initiate', [PaymentGatewayController::class, 'initiatePayment'])->name('payment.initiate');         // R17
Route::match(['get', 'post'], '/payment/success', [PaymentGatewayController::class, 'handleSuccess'])->name('payment.success'); // R17

Route::match(['get', 'post'], '/coming-soon/{feature?}', [ComingSoonController::class, 'show'])->name('coming-soon');
Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::patch('/profile', [ProfileController::class, 'update']);
Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
Route::get('/settings', [ProfileController::class, 'show'])->name('settings.show');
Route::get('/api/profile', [ProfileController::class, 'show']);
Route::put('/api/profile', [ProfileController::class, 'update']);
Route::delete('/api/profile', [ProfileController::class, 'destroy']);
Route::get('/notifications', fn (Request $r) => app(ComingSoonController::class)->show($r, 'multi-channel-alerts'));
Route::get('/support', fn (Request $r) => app(ComingSoonController::class)->show($r, 'support-desk'));
Route::get('/help', fn (Request $r) => app(ComingSoonController::class)->show($r, 'support-desk'));
Route::get('/terms', fn (Request $r) => app(ComingSoonController::class)->show($r, 'terms-of-service'));
Route::get('/privacy', fn (Request $r) => app(ComingSoonController::class)->show($r, 'privacy-policy'));
Route::get('/about', fn (Request $r) => app(ComingSoonController::class)->show($r, 'about-meramot'));

Route::fallback([ComingSoonController::class, 'fallback']);
