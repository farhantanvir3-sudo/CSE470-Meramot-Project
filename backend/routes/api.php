<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VendorOnboardingController;
use App\Http\Controllers\VendorBranchController;
use App\Http\Controllers\BiddingEngineController;
use App\Http\Controllers\AISummaryController;
use App\Http\Controllers\VendorNotificationController;
use App\Http\Controllers\VendorAnalyticsController;
use App\Http\Controllers\ShopDiscoveryController;
use App\Http\Controllers\AIChatController;

/*
|--------------------------------------------------------------------------
| API Routes — Meramot Digital Marketplace
|--------------------------------------------------------------------------
| CSE470 Software Engineering (Section 06) — Group 6
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Feature R6: Shop Search with Nationwide Distance & Multi-Branch Filtering
Route::prefix('shops')->group(function () {
    Route::get('/nearby', [ShopDiscoveryController::class, 'getNearbyShops']); // Distance-based search across Bangladesh
    Route::get('/search', [ShopDiscoveryController::class, 'apiSearch']); // API search endpoint
});

// Feature R10: AI-Powered Repair Diagnostic Chat
Route::prefix('ai-consultant')->group(function () {
    Route::post('/message', [AIChatController::class, 'sendMessage']);
});

// Feature R11 & R15: Vendor Onboarding, Dual-Address & Multi-Branch Expansion
Route::prefix('vendors')->group(function () {
    Route::get('/onboarding', [VendorOnboardingController::class, 'show']);
    Route::post('/onboarding', [VendorOnboardingController::class, 'store']);
    Route::post('/branches/toggle', [VendorBranchController::class, 'enableBranching']);
    Route::post('/branches', [VendorBranchController::class, 'storeBranch']);
    Route::patch('/branches/{shopId}', [VendorBranchController::class, 'updateBranchAddresses']);
    Route::post('/{id}/verify', [VendorOnboardingController::class, 'verify']);
    Route::get('/{id}/analytics', [VendorAnalyticsController::class, 'getPerformanceMetrics']); // Feature R15
});

// Feature R12: Live Bidding Engine & Diagnostic Quotes
Route::prefix('requests/{requestId}')->group(function () {
    Route::get('/bids', [BiddingEngineController::class, 'index']);
    Route::post('/bids', [BiddingEngineController::class, 'store']);
    Route::post('/summarize-ai', [AISummaryController::class, 'generateSummary']); // Feature R13
    Route::post('/notify-vendors', [VendorNotificationController::class, 'dispatchBiddingAlert']); // Feature R14
});

Route::post('/bids/{bidId}/accept', [BiddingEngineController::class, 'acceptBid']);
