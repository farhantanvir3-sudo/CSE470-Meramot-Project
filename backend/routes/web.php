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
use App\Http\Controllers\ServicePackageController;     // R8 - Shanid
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

/*
|--------------------------------------------------------------------------
| Web Routes — Meramot Digital Marketplace (CSE 470 Group 6)
|--------------------------------------------------------------------------
*/

Route::get('/', [ShopDiscoveryController::class, 'home'])->name('home');

// -------------------------------------------------------------
// MODULE: Delivery & Rider Logistics (Assigned: Md. Nafiul Islam)
// -------------------------------------------------------------
Route::prefix('delivery')->group(function () {
    Route::get('/jobs-queue', [DeliveryJobController::class, 'index']);                 // R2
    Route::post('/jobs/{id}/claim', [DeliveryJobController::class, 'claimJob']);        // R2
    Route::post('/jobs/{id}/milestone', [DeliveryJobController::class, 'updateMilestone']); // R2
    Route::get('/earnings', [RiderLedgerController::class, 'index']);                   // R3
    Route::post('/payout/request', [RiderLedgerController::class, 'requestPayout']);    // R3
});
Route::get('/admin/delivery-agents/pending', [AdminDeliveryController::class, 'index']); // R1
Route::post('/admin/delivery-agents/{id}/approve', [AdminDeliveryController::class, 'approve']); // R1
Route::post('/admin/delivery-agents/{id}/reject', [AdminDeliveryController::class, 'reject']);   // R1
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
Route::get('/packages/catalog', [ServicePackageController::class, 'index']);            // R8
Route::post('/cart/add', [ServicePackageController::class, 'addToCart']);               // R8
Route::get('/reviews/create/{shopId}', [ReviewController::class, 'create']);            // R9
Route::post('/reviews', [ReviewController::class, 'store']);                            // R9
Route::get('/ai-consultant/chat', [AIChatController::class, 'index']);                  // R10

// -------------------------------------------------------------
// MODULE: Vendor Onboarding, Bidding & Analytics (Assigned: Farhan Tanvir - Lead)
// -------------------------------------------------------------
Route::prefix('vendor')->group(function () {
    Route::get('/onboarding', [VendorOnboardingController::class, 'show']);             // R11
    Route::get('/bidding-board', [BiddingEngineController::class, 'index']);            // R12
    Route::get('/request-details/{id}', function($id) { return view('vendor.request-details'); }); // R13
    Route::get('/alert-settings', function() { return view('vendor.alert-settings'); }); // R14
    Route::get('/dashboard', function() { return view('vendor.dashboard'); });          // R15
});

// -------------------------------------------------------------
// MODULE: Finance, Payments & Governance (Assigned: Md. Nawshad Zaman Khan)
// -------------------------------------------------------------
Route::prefix('admin')->group(function () {
    Route::get('/financial-overview', [AdminLedgerController::class, 'index']);         // R16
    Route::get('/payouts/manage', [VendorPayoutController::class, 'index']);             // R18
    Route::post('/payouts/{id}/disburse', [VendorPayoutController::class, 'disbursePayout']); // R18
    Route::get('/moderation', [AdminModerationController::class, 'index']);             // R19
    Route::post('/moderation/sanction', [AdminModerationController::class, 'applySanction']); // R19
    Route::get('/audit-logs', [AuditLogController::class, 'index']);                    // R20
});
Route::get('/checkout/payment/{requestId}', [PaymentGatewayController::class, 'showCheckout']); // R17
Route::post('/payment/initiate', [PaymentGatewayController::class, 'initiatePayment']); // R17
