<?php

use Illuminate\Support\Facades\Route;

// Import all single-CRUD controllers for R1 to R20
use App\Http\Controllers\AdminDeliveryController;      // R1 - Nafiul
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
use App\Http\Controllers\BiddingEngineController;       // R12 - Farhan
use App\Http\Controllers\AISummaryController;          // R13 - Farhan
use App\Http\Controllers\VendorNotificationController; // R14 - Farhan
use App\Http\Controllers\VendorAnalyticsController;    // R15 - Farhan

use App\Http\Controllers\AdminLedgerController;        // R16 - Nawshad
use App\Http\Controllers\PaymentGatewayController;     // R17 - Nawshad
use App\Http\Controllers\VendorPayoutController;       // R18 - Nawshad
use App\Http\Controllers\AdminModerationController;    // R19 - Nawshad
use App\Http\Controllers\AuditLogController;           // R20 - Nawshad
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FrontendAssetController;
use App\Models\RepairJob;
use App\Models\RepairRequest;

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

// -------------------------------------------------------------
// MODULE: Delivery & Rider Logistics (Assigned: Md. Nafiul Islam [22299035])
// -------------------------------------------------------------
Route::prefix('delivery')->group(function () {
    Route::get('/jobs-queue', [DeliveryJobController::class, 'index']);                 // R2
    Route::post('/jobs/{id}/claim', [DeliveryJobController::class, 'claimJob']);        // R2
    Route::post('/jobs/{id}/milestone', [DeliveryJobController::class, 'updateMilestone']); // R2
    Route::get('/earnings', [RiderLedgerController::class, 'index']);                   // R3
    Route::post('/payout/request', [RiderLedgerController::class, 'requestPayout']);    // R3
});
// Feature R1 Routes (Added by Md. Nafiul Islam [22299035] in c6c0781 & fdf5e6f)
Route::get('/admin/delivery-agents/pending', [AdminDeliveryController::class, 'index'])->name('admin.delivery-agents.pending'); // R1
Route::post('/admin/delivery-agents/{id}/approve', [AdminDeliveryController::class, 'approve'])->name('admin.delivery-agents.approve'); // R1
Route::post('/admin/delivery-agents/{id}/reject', [AdminDeliveryController::class, 'reject'])->name('admin.delivery-agents.reject');   // R1
Route::post('/admin/delivery-agents/{id}/block', [AdminDeliveryController::class, 'block'])->name('admin.delivery-agents.block');       // R1
Route::get('/delivery-agent/register', [AdminDeliveryController::class, 'create'])->name('delivery.register'); // R1
Route::post('/delivery-agent/register', [AdminDeliveryController::class, 'store'])->name('delivery.register.store'); // R1
Route::get('/tracking/{jobId}', [TrackingController::class, 'showMap']);                // R4
Route::get('/support/rider-chat/{riderId}', [RiderChatController::class, 'index']);    // R5

// -------------------------------------------------------------
// MODULE: Discovery, Requests & AI Consultation (Assigned: Shanid Sajjatuz Islam)
// -------------------------------------------------------------
Route::get('/shops', [ShopDiscoveryController::class, 'index'])->name('shops.index');
Route::get('/shops/explore', [ShopDiscoveryController::class, 'index'])->name('shops.explore');
Route::get('/shops/{slug}', [ShopDiscoveryController::class, 'show'])->name('shops.show'); // R6

Route::get('/requests/create', [RepairRequestController::class, 'create']);             // R7
Route::post('/requests', [RepairRequestController::class, 'store']);                    // R7
Route::get('/requests/{id}/track', [RepairRequestController::class, 'track'])->name('requests.track'); // R7
Route::post('/requests/{id}/update-status', [StatusTransitionController::class, 'updateStatus']); // R7
Route::get('/requests/{id}/available-transitions', [StatusTransitionController::class, 'getAvailableTransitions']); // R7
Route::post('/requests/{id}/accept-bid', [StatusTransitionController::class, 'acceptBid'])->name('requests.accept-bid'); // R7
Route::post('/requests/{id}/respond-final-quote', [StatusTransitionController::class, 'respondToFinalQuote'])->name('requests.respond-final-quote'); // R7 Quote 2
Route::post('/requests/{id}/cancel', [StatusTransitionController::class, 'cancel'])->name('requests.cancel'); // R7

Route::get('/packages/catalog', [ServicePackageController::class, 'index'])->name('packages.catalog'); // R8
Route::post('/cart/add', [ServicePackageController::class, 'addToCart'])->name('cart.add'); // R8
Route::post('/cart/shop', [ServicePackageController::class, 'selectCartShop'])->name('cart.shop'); // R8 Quickmatch shop
Route::get('/cart', [ServicePackageController::class, 'viewCart'])->name('cart.view'); // R8
Route::delete('/cart/remove/{packageId}', [ServicePackageController::class, 'removeFromCart'])->name('cart.remove'); // R8
Route::patch('/cart/update/{packageId}', [ServicePackageController::class, 'updateCartQuantity'])->name('cart.update'); // R8
Route::post('/cart/clear', [ServicePackageController::class, 'clearCart'])->name('cart.clear'); // R8
Route::get('/cart/checkout', [ServicePackageController::class, 'checkout'])->name('cart.checkout'); // R8
Route::post('/cart/checkout/place', [ServicePackageController::class, 'placeDirectOrder'])->name('cart.place'); // R8 Direct Order

Route::get('/reviews/create/{shopId}', [ReviewController::class, 'create']);            // R9
Route::post('/reviews', [ReviewController::class, 'store']);                            // R9
Route::get('/ai-consultant/chat', [AIChatController::class, 'index']);                  // R10

// -------------------------------------------------------------
// MODULE: Vendor Onboarding, Bidding & Analytics (Assigned: Farhan Tanvir - Lead)
// -------------------------------------------------------------
Route::prefix('vendor')->group(function () {
    Route::get('/onboarding', [VendorOnboardingController::class, 'show']);             // R11
    Route::get('/bidding-board', [BiddingEngineController::class, 'index']);            // R12
    Route::get('/request-details/{id}', function ($id) { return view('vendor.request-details'); }); // R13
    Route::get('/alert-settings', function () { return view('vendor.alert-settings'); }); // R14
    Route::get('/dashboard', function () {
        $directOrders = RepairRequest::with(['customer', 'requestedShop'])
            ->where('source', 'DIRECT_SERVICE')
            ->where('status', 'PENDING')
            ->latest()
            ->get();
        $marketplaceRequests = RepairRequest::with('customer')
            ->where('status', 'BIDDING')
            ->latest()
            ->take(6)
            ->get();
        $activeJobs = RepairJob::with(['repairRequest', 'shop'])
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->latest()
            ->take(8)
            ->get();
        return view('vendor.dashboard', compact('directOrders', 'marketplaceRequests', 'activeJobs'));
    }); // R15
    Route::post('/direct-orders/{id}/respond', [StatusTransitionController::class, 'respondToDirectOrder'])->name('vendor.direct-orders.respond');
});

// -------------------------------------------------------------
// MODULE: Finance, Payments & Governance (Assigned: Md. Nawshad Zaman Khan [23301622])
// -------------------------------------------------------------
Route::prefix('admin')->group(function () {
    // Feature R16: Platform Financial Summary & Double-Entry Ledger (Md. Nawshad Zaman Khan [23301622] — Commit 4742c9c)
    Route::get('/financial-overview', [AdminLedgerController::class, 'index'])->name('admin.financial-overview');         // R16
    Route::post('/financial-overview/settle', [AdminLedgerController::class, 'settleManual'])->name('admin.financial-overview.settle'); // R16
    Route::post('/financial-overview/auto-settle', [AdminLedgerController::class, 'autoSettleBatch'])->name('admin.financial-overview.auto-settle'); // R16
    Route::get('/payouts/manage', [VendorPayoutController::class, 'index']);             // R18
    Route::post('/payouts/{id}/disburse', [VendorPayoutController::class, 'disbursePayout']); // R18
    Route::get('/moderation', [AdminModerationController::class, 'index']);             // R19
    Route::post('/moderation/sanction', [AdminModerationController::class, 'applySanction']); // R19
    Route::get('/audit-logs', [AuditLogController::class, 'index']);                    // R20
});
Route::get('/checkout/payment/{requestId}', [PaymentGatewayController::class, 'showCheckout']); // R17
Route::post('/payment/initiate', [PaymentGatewayController::class, 'initiatePayment']); // R17
