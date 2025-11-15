<?php

use App\Http\Controllers\Api\KpiController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\SystemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// System health and stats
Route::get('/health', [SystemController::class, 'health']);
Route::get('/stats', [SystemController::class, 'stats']);

// KPI endpoints
Route::prefix('kpis')->group(function () {
    Route::get('/daily', [KpiController::class, 'daily']);
    Route::get('/range', [KpiController::class, 'range']);
    Route::get('/realtime', [KpiController::class, 'realtime']);
});

// Leaderboard endpoints
Route::prefix('leaderboard')->group(function () {
    Route::get('/customers', [LeaderboardController::class, 'customers']);
    Route::get('/customers/{customerId}/rank', [LeaderboardController::class, 'customerRank']);
    Route::get('/products', [LeaderboardController::class, 'products']);
    Route::post('/rebuild', [LeaderboardController::class, 'rebuild']);
});

// Notification endpoints
Route::prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/order/{orderId}', [NotificationController::class, 'byOrder']);
    Route::get('/stats', [NotificationController::class, 'stats']);
    Route::get('/recent', [NotificationController::class, 'recent']);
    Route::post('/{id}/resend', [NotificationController::class, 'resend']);
});

// Refund endpoints
Route::prefix('refunds')->group(function () {
    Route::post('/', [RefundController::class, 'create']);
    Route::get('/', [RefundController::class, 'index']);
    Route::get('/stats', [RefundController::class, 'stats']);
    Route::get('/{refundId}', [RefundController::class, 'show']);
    Route::get('/order/{orderId}', [RefundController::class, 'byOrder']);
    Route::post('/{refundId}/cancel', [RefundController::class, 'cancel']);
    Route::post('/{refundId}/retry', [RefundController::class, 'retry']);
});

