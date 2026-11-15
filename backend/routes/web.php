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
use App\Http\Controllers\SparePartController;          // Dedicated Spare Parts Marketplace (Mobile + PC/Laptop)
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
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:25,1');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:25,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/auth/demo', [AuthController::class, 'demoLogin'])->name('auth.demo');
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::get('/verify-email-otp', [AuthController::class, 'showVerifyOtp'])->name('auth.verify-otp.form');
Route::post('/verify-email-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:25,1')->name('auth.verify-otp.submit');
Route::post('/verify-email-otp/send', [AuthController::class, 'sendVerificationOtp'])->middleware('throttle:25,1')->name('auth.verify-otp.send');
Route::post('/api/verification/send-otp', [AuthController::class, 'sendVerificationOtp'])->middleware('throttle:25,1');
Route::post('/api/verification/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:25,1');
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::get('/reset-password', [AuthController::class, 'showForgotPassword'])->name('password.reset.form');
Route::post('/forgot-password', [AuthController::class, 'sendForgotPasswordEmail'])->middleware('throttle:25,1')->name('password.email');
Route::post('/forgot-password/verify-otp', [AuthController::class, 'verifyForgotPasswordOtp'])->middleware('throttle:25,1')->name('password.verify-otp');
Route::post('/forgot-password/reset', [AuthController::class, 'resetPasswordWithOtp'])->middleware('throttle:25,1')->name('password.update');

// -------------------------------------------------------------
// MODULE: Delivery & Rider Logistics (Assigned: Md. Nafiul Islam [22299035])
// -------------------------------------------------------------
Route::prefix('delivery')->group(function () {
    Route::get('/login', [DeliveryPortalController::class, 'showLogin'])->name('delivery.login');       // R1 Company Login
    Route::post('/login', [DeliveryPortalController::class, 'login'])->middleware('throttle:25,1')->name('delivery.login.submit');   // R1 Company Login
    Route::post('/forgot-credentials', [DeliveryPortalController::class, 'sendForgotCredentials'])->middleware('throttle:25,1')->name('delivery.forgot-credentials');
    Route::post('/logout', [DeliveryPortalController::class, 'logout'])->name('delivery.logout');       // R1 Sign Out
    Route::get('/', [DeliveryPortalController::class, 'dashboard'])->name('delivery.dashboard');        // R1/R2 Rider Dashboard
    Route::get('/map', [DeliveryPortalController::class, 'map'])->name('delivery.map');                 // R4 Live Rider Map
    Route::post('/location', [DeliveryPortalController::class, 'updateLocation'])->name('delivery.location'); // R4 GPS Sync
    Route::post('/location/simulate', [DeliveryPortalController::class, 'simulateRouteStep'])->name('delivery.location.simulate'); // R4 GPS Route Simulator
    Route::get('/order/{id}', [DeliveryPortalController::class, 'orderDetails'])->name('delivery.order.show'); // R2 Order Details
    Route::post('/order/{id}/status', [DeliveryPortalController::class, 'advanceOrderStatus'])->name('delivery.order.status'); // R2 Milestone
    Route::get('/profile', [DeliveryPortalController::class, 'profile'])->name('delivery.profile');     // R1 Rider Profile

    Route::get('/jobs-queue', [DeliveryJobController::class, 'index'])->name('delivery.jobs-queue');   // R2
    Route::post('/jobs/{id}/claim', [DeliveryJobController::class, 'claimJob'])->name('delivery.jobs.claim'); // R2
    Route::post('/jobs/{id}/milestone', [DeliveryJobController::class, 'updateMilestone'])->name('delivery.jobs.milestone'); // R2
    Route::post('/jobs/{id}/release', [DeliveryJobController::class, 'releaseJob'])->name('delivery.jobs.release'); // R2
    Route::post('/jobs/{id}/exception', [DeliveryJobController::class, 'reportException'])->name('delivery.jobs.exception'); // R2

    Route::get('/earnings', [RiderLedgerController::class, 'index'])->name('delivery.earnings');       // R3
    Route::post('/payout/settings', [RiderLedgerController::class, 'savePayoutSettings'])->name('delivery.payout.settings'); // R3
    Route::post('/payout/request', [RiderLedgerController::class, 'requestPayout'])->name('delivery.payout.request'); // R3
});

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
Route::post('/delivery-agent/status/send-otp', [AdminDeliveryController::class, 'sendStatusOtp'])->middleware('throttle:25,1')->name('delivery.status.send-otp');      // R1
Route::post('/delivery-agent/status/verify-otp', [AdminDeliveryController::class, 'verifyStatusOtp'])->middleware('throttle:25,1')->name('delivery.status.verify-otp'); // R1
Route::post('/delivery-agent/status/reset', [AdminDeliveryController::class, 'resetStatusCheck'])->name('delivery.status.reset');          // R1
Route::post('/delivery-agent/status/resend-credentials', [AdminDeliveryController::class, 'resendCompanyCredentials'])->name('delivery.status.resend-credentials'); // R1 Forgot Credentials
Route::get('/delivery-agent/edit', [AdminDeliveryController::class, 'editApplication'])->name('delivery.edit');                           // R1 Unlimited Edit
Route::post('/delivery-agent/edit', [AdminDeliveryController::class, 'updateApplication'])->name('delivery.edit.update');                 // R1

Route::get('/tracking/{jobId}', [TrackingController::class, 'showMap'])->name('tracking.show');        // R4
Route::get('/tracking/{jobId}/live', [TrackingController::class, 'liveTelemetry'])->name('tracking.live'); // R4 Live Polling
Route::get('/support/rider-chat/{riderId}', [RiderChatController::class, 'index']);                    // R5
Route::post('/support/rider-chat/send', [RiderChatController::class, 'sendMessage']);                  // R5
Route::post('/api/support/rider-chat/send', [RiderChatController::class, 'sendMessage']);              // R5

// -------------------------------------------------------------
// MODULE: Discovery, Requests & AI Consultation (Assigned: Shanid Sajjatuz Islam)
// -------------------------------------------------------------
Route::get('/shops', [ShopDiscoveryController::class, 'index'])->name('shops.index');
Route::get('/shops/explore', [ShopDiscoveryController::class, 'index'])->name('shops.explore');
Route::get('/shops/{slug}', [ShopDiscoveryController::class, 'show'])->name('shops.show'); // R6

// Dedicated Spare Parts Marketplace (Items w/ Seller Shop, Specs, Wholesale & Sale Offers)
Route::get('/spare-parts', [SparePartController::class, 'index'])->name('spare-parts.index');
Route::get('/spare-parts/{slug}', [SparePartController::class, 'show'])->name('spare-parts.show');

Route::get('/requests', [RepairRequestController::class, 'index'])->name('requests.index');            // R7 Customer Hub
Route::get('/orders', [RepairRequestController::class, 'index'])->name('orders.index');                // R7 Alias
Route::get('/requests/create', [RepairRequestController::class, 'create'])->name('requests.create');   // R7
Route::post('/requests', [RepairRequestController::class, 'store'])->name('requests.store');           // R7
Route::get('/requests/{id}/track', [RepairRequestController::class, 'track'])->name('requests.track'); // R7
Route::get('/requests/{requestId}/invoice', [PaymentGatewayController::class, 'showRequestInvoice'])->name('requests.invoice'); // R17
Route::post('/requests/{id}/bids', [VendorBranchController::class, 'submitBid']);                      // R7/R12 Web Bid Alias
Route::post('/requests/{id}/update-status', [StatusTransitionController::class, 'updateStatus']);      // R7
Route::get('/requests/{id}/available-transitions', [StatusTransitionController::class, 'getAvailableTransitions']); // R7
Route::post('/requests/{id}/accept-bid', [StatusTransitionController::class, 'acceptBid'])->name('requests.accept-bid'); // R7
Route::post('/requests/{id}/respond-final-quote', [StatusTransitionController::class, 'respondToFinalQuote'])->name('requests.respond-final-quote'); // R7 Quote 2
Route::post('/requests/{id}/cancel', [StatusTransitionController::class, 'cancel'])->name('requests.cancel'); // R7

Route::post('/location/sync', [ServicePackageController::class, 'syncCustomerLocation'])->name('location.sync');
Route::get('/packages/catalog', [ServicePackageController::class, 'index'])->name('packages.catalog'); // R8
Route::post('/cart/add', [ServicePackageController::class, 'addToCart'])->name('cart.add');            // R8
Route::post('/cart/shop', [ServicePackageController::class, 'selectCartShop'])->name('cart.shop');     // R8 Quickmatch shop
Route::get('/cart', [ServicePackageController::class, 'viewCart'])->name('cart.view');                 // R8
Route::delete('/cart/remove/{packageId}', [ServicePackageController::class, 'removeFromCart'])->name('cart.remove'); // R8
Route::patch('/cart/update/{packageId}', [ServicePackageController::class, 'updateCartQuantity'])->name('cart.update'); // R8
Route::post('/cart/clear', [ServicePackageController::class, 'clearCart'])->name('cart.clear');        // R8
Route::post('/cart/voucher/apply', [ServicePackageController::class, 'applyVoucher'])->name('cart.voucher.apply');   // R8
Route::post('/cart/voucher/remove', [ServicePackageController::class, 'removeVoucher'])->name('cart.voucher.remove'); // R8
Route::get('/cart/checkout', [ServicePackageController::class, 'checkout'])->name('cart.checkout');    // R8
Route::post('/cart/checkout/place', [ServicePackageController::class, 'placeDirectOrder'])->name('cart.place'); // R8 Direct Order

Route::get('/reviews/create/{shopId}', [ReviewController::class, 'create'])->name('reviews.create');   // R9
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');                    // R9
Route::put('/reviews/{id}', [ReviewController::class, 'update'])->name('reviews.update');              // R9 Edit own review
Route::delete('/reviews/{id}', [ReviewController::class, 'destroy'])->name('reviews.destroy');         // R9 Delete own review
Route::post('/reviews/bulk-delete', [ReviewController::class, 'bulkDestroy'])->name('reviews.bulk-destroy'); // R9 Bulk delete own reviews
Route::get('/ai-consultant/chat', [AIChatController::class, 'index'])->name('ai-consultant.chat');     // R10

// -------------------------------------------------------------
// MODULE: Vendor Onboarding, Bidding, AI Triage & Alerts (Assigned: Farhan Tanvir - Lead)
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
    Route::post('/bids/{bidId}/withdraw', [BiddingEngineController::class, 'withdrawBid'])->name('vendor.bids.withdraw'); // R12

    Route::get('/request-details/{id}', [AISummaryController::class, 'showRequestDetails'])->name('vendor.request-details'); // R13
    Route::post('/request-details/{id}/regenerate', [AISummaryController::class, 'regenerateSummary'])->name('vendor.request-details.regenerate'); // R13

    Route::get('/alert-settings', [VendorNotificationController::class, 'settings'])->name('vendor.alert-settings'); // R14
    Route::post('/alert-settings', [VendorNotificationController::class, 'updateSettings'])->name('vendor.alert-settings.update'); // R14
    Route::post('/alert-settings/test', [VendorNotificationController::class, 'sendTestAlert'])->name('vendor.alert-settings.test'); // R14

    Route::get('/dashboard', [VendorBranchController::class, 'dashboard'])->name('vendor.dashboard');              // R15 + Multi-Branch + R18 Payouts Tab
    Route::post('/branches/toggle', [VendorBranchController::class, 'enableBranching'])->name('vendor.branches.toggle');
    Route::post('/branches', [VendorBranchController::class, 'storeBranch'])->name('vendor.branches.store');
    Route::patch('/branches/{shopId}', [VendorBranchController::class, 'updateBranchAddresses'])->name('vendor.branches.update');
    Route::post('/direct-orders/{id}/respond', [StatusTransitionController::class, 'respondToDirectOrder'])->name('vendor.direct-orders.respond');
    Route::post('/requests/{id}/bid', [VendorBranchController::class, 'submitBid'])->name('vendor.requests.bid');  // R12 Real Web Bid
    Route::post('/jobs/{jobId}/status', [VendorBranchController::class, 'updateJobStatus'])->name('vendor.jobs.status'); // Bench Status + R16 Settlement

    Route::post('/payouts/settings', [VendorPayoutController::class, 'saveVendorSettings'])->name('vendor.payouts.settings'); // R18
    Route::post('/payouts/request', [VendorPayoutController::class, 'requestVendorPayout'])->name('vendor.payouts.request');   // R18

    // Vendor Spare Parts Inventory, Spec Options, Sale Offers & Wholesale Tiers
    Route::post('/spare-parts', [SparePartController::class, 'store'])->name('vendor.spare-parts.store');
    Route::put('/spare-parts/{id}', [SparePartController::class, 'update'])->name('vendor.spare-parts.update');
    Route::post('/spare-parts/{id}/toggle', [SparePartController::class, 'toggleStatus'])->name('vendor.spare-parts.toggle');
    Route::delete('/spare-parts/{id}', [SparePartController::class, 'destroy'])->name('vendor.spare-parts.destroy');
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

    // Feature R16: Platform Financial Summary & Double-Entry Ledger (Md. Nawshad Zaman Khan [23301622])
    Route::get('/financial-overview', [AdminLedgerController::class, 'index'])->name('admin.financial-overview');         // R16
    Route::post('/financial-overview/settle', [AdminLedgerController::class, 'settleManual'])->name('admin.financial-overview.settle'); // R16
    Route::post('/financial-overview/auto-settle', [AdminLedgerController::class, 'autoSettleBatch'])->name('admin.financial-overview.auto-settle'); // R16
    Route::post('/financial-overview/bulk-delete', [AdminLedgerController::class, 'bulkDelete'])->name('admin.financial-overview.bulk-delete'); // R16
    Route::post('/financial-overview/issue-refund', [AdminLedgerController::class, 'issueRefund'])->name('admin.financial-overview.issue-refund'); // R16
    Route::post('/shops/{shop}/commission-rate', [AdminLedgerController::class, 'updateShopCommissionRate'])->name('admin.shops.commission-rate'); // R16 Dynamic 5-10% Commission

    // Feature R18: Automated Vendor & Courier Payout Settlements
    Route::get('/payouts/manage', [VendorPayoutController::class, 'index'])->name('admin.payouts.manage');                // R18
    Route::post('/payouts/settle-shop', [VendorPayoutController::class, 'settleShopDirect'])->name('admin.payouts.settle-shop'); // R18
    Route::post('/payouts/{id}/disburse', [VendorPayoutController::class, 'disbursePayout'])->name('admin.payouts.disburse'); // R18

    // Feature R19: Sitewide User Moderation, Dispute Arbitration & Support Escalations
    Route::get('/moderation', [AdminModerationController::class, 'index'])->name('admin.moderation');                     // R19
    Route::post('/moderation/sanction', [AdminModerationController::class, 'applySanction'])->name('admin.moderation.sanction'); // R19
    Route::post('/disputes/{id}/resolve', [AdminModerationController::class, 'resolveDispute'])->name('admin.disputes.resolve'); // R19
    Route::post('/support/{id}/resolve', [AdminModerationController::class, 'resolveSupportTicket'])->name('admin.support.resolve'); // R19
    Route::post('/reviews/{reviewId}/moderate', [AdminModerationController::class, 'moderateReview'])->name('admin.reviews.moderate'); // R19
    Route::post('/users/{userId}/ban', [AdminModerationController::class, 'banUser'])->name('admin.users.ban');          // R19
    Route::post('/users/{userId}/unban', [AdminModerationController::class, 'unbanUser'])->name('admin.users.unban');    // R19

    // Feature R20: Security Audit Trail (Sprint 4)
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs');                            // R20
});

// -------------------------------------------------------------
// Payment Checkout, Escrow Sandbox & PDF Invoices (R17 — Sprint 2)
// -------------------------------------------------------------
Route::get('/checkout/payment/{requestId}', [PaymentGatewayController::class, 'showCheckout'])->name('payment.checkout'); // R17
Route::get('/payment/checkout/{requestId}', [PaymentGatewayController::class, 'showCheckout'])->name('checkout.payment'); // R17 Alias
Route::post('/payment/initiate', [PaymentGatewayController::class, 'initiatePayment'])->name('payment.initiate');         // R17
Route::get('/payment/sandbox/{transactionRef}', [PaymentGatewayController::class, 'showSandbox'])->name('payment.sandbox'); // R17
Route::match(['get', 'post'], '/payment/success', [PaymentGatewayController::class, 'handleSuccess'])->name('payment.success'); // R17
Route::match(['get', 'post'], '/payment/fail', [PaymentGatewayController::class, 'handleFail'])->name('payment.fail');    // R17
Route::match(['get', 'post'], '/payment/cancel', [PaymentGatewayController::class, 'handleCancel'])->name('payment.cancel'); // R17
Route::post('/payment/ipn', [PaymentGatewayController::class, 'handleIpn'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('payment.ipn'); // R17
Route::get('/invoices/{invoice}', [PaymentGatewayController::class, 'showInvoice'])->name('invoices.show');               // R17
Route::get('/invoices/{invoice}/pdf', [PaymentGatewayController::class, 'downloadPdf'])->name('invoices.pdf');            // R17

// -------------------------------------------------------------
// Notifications (R14), Support & Escrow Disputes (R19), Profile & Roadmap Endpoints
// -------------------------------------------------------------
Route::match(['get', 'post'], '/coming-soon/{feature?}', [ComingSoonController::class, 'show'])->name('coming-soon');
Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::patch('/profile', [ProfileController::class, 'update']);
Route::match(['post', 'put'], '/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
Route::get('/settings', [ProfileController::class, 'show'])->name('settings.show');
Route::get('/api/profile', [ProfileController::class, 'show']);
Route::put('/api/profile', [ProfileController::class, 'update']);
Route::delete('/api/profile', [ProfileController::class, 'destroy']);

Route::get('/notifications', [VendorNotificationController::class, 'notificationCenter'])->name('notifications.index');   // R14
Route::post('/notifications/mark-read', [ProfileController::class, 'markNotificationsRead'])->name('notifications.mark-read');

Route::get('/support', [AdminModerationController::class, 'supportDesk'])->name('support.index');                         // R19
Route::get('/help', [AdminModerationController::class, 'supportDesk'])->name('support.help');                             // R19
Route::post('/disputes', [AdminModerationController::class, 'storeDispute'])->name('disputes.store');                     // R19
Route::post('/disputes/{id}/notes', [AdminModerationController::class, 'addDisputeNote'])->name('disputes.notes.store');  // R19
Route::post('/support/tickets', [AdminModerationController::class, 'storeSupportTicket'])->name('support.tickets.store'); // R19
Route::post('/support/tickets/{id}/reply', [AdminModerationController::class, 'replySupportTicket'])->name('support.tickets.reply'); // R19

Route::get('/terms', fn (Request $r) => app(ComingSoonController::class)->show($r, 'terms-of-service'));
Route::get('/privacy', fn (Request $r) => app(ComingSoonController::class)->show($r, 'privacy-policy'));
Route::get('/about', fn (Request $r) => app(ComingSoonController::class)->show($r, 'about-meramot'));

Route::fallback([ComingSoonController::class, 'fallback']);
