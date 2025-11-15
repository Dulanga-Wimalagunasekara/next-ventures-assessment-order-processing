<?php

use App\Http\Controllers\Api\KpiController;
use App\Http\Controllers\Api\LeaderboardController;
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

