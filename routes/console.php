<?php

use App\Services\KpiService;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generate daily KPIs every hour
Schedule::call(function () {
    $kpiService = app(KpiService::class);
    $kpiService->generateDailyKpis();
    $kpiService->updateRealTimeKpis();
})->hourly()->name('generate-hourly-kpis');

// Rebuild customer leaderboard every 30 minutes
Schedule::call(function () {
    $leaderboardService = app(LeaderboardService::class);
    $leaderboardService->rebuildCustomerLeaderboard(100);
})->everyThirtyMinutes()->name('rebuild-customer-leaderboard');

// Take Horizon snapshots for metrics
Schedule::command('horizon:snapshot')->everyFiveMinutes();

