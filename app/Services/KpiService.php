<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class KpiService
{
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Generate and cache daily KPIs
     */
    public function generateDailyKpis(string $date = null): array
    {
        $date = $date ?? now()->format('Y-m-d');
        $cacheKey = "kpis:daily:{$date}";

        // Check if cached
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        // Calculate KPIs
        $orders = Order::whereDate('order_date', $date)
            ->where('status', 'completed')
            ->get();

        $kpis = [
            'date' => $date,
            'total_revenue' => $orders->sum('total_amount'),
            'order_count' => $orders->count(),
            'average_order_value' => $orders->count() > 0 ? $orders->avg('total_amount') : 0,
            'unique_customers' => $orders->unique('customer_id')->count(),
            'generated_at' => now()->toIso8601String(),
        ];

        // Cache the results
        Redis::setex($cacheKey, self::CACHE_TTL, json_encode($kpis));

        return $kpis;
    }

    /**
     * Get KPIs for a date range
     */
    public function getKpisForRange(string $startDate, string $endDate): array
    {
        $cacheKey = "kpis:range:{$startDate}:{$endDate}";

        $cached = Redis::get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        $orders = Order::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->get();

        $kpis = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_revenue' => $orders->sum('total_amount'),
            'order_count' => $orders->count(),
            'average_order_value' => $orders->count() > 0 ? $orders->avg('total_amount') : 0,
            'unique_customers' => $orders->unique('customer_id')->count(),
            'generated_at' => now()->toIso8601String(),
        ];

        Redis::setex($cacheKey, 3600, json_encode($kpis)); // 1 hour cache

        return $kpis;
    }

    /**
     * Get real-time KPI metrics from Redis
     */
    public function getRealTimeKpis(): array
    {
        return [
            'pending_orders' => (int)Redis::get('kpis:realtime:pending_orders') ?? 0,
            'processing_orders' => (int)Redis::get('kpis:realtime:processing_orders') ?? 0,
            'completed_today' => (int)Redis::get('kpis:realtime:completed_today') ?? 0,
            'failed_today' => (int)Redis::get('kpis:realtime:failed_today') ?? 0,
            'revenue_today' => (float)Redis::get('kpis:realtime:revenue_today') ?? 0.0,
        ];
    }

    /**
     * Update real-time KPI metrics
     */
    public function updateRealTimeKpis(): void
    {
        $today = now()->format('Y-m-d');

        $pending = Order::where('status', 'pending')->count();
        $processing = Order::whereIn('status', ['reserved', 'payment_processing'])->count();
        $completedToday = Order::whereDate('order_date', $today)->where('status', 'completed')->count();
        $failedToday = Order::whereDate('order_date', $today)->whereIn('status', ['failed', 'rollback'])->count();
        $revenueToday = Order::whereDate('order_date', $today)->where('status', 'completed')->sum('total_amount');

        Redis::setex('kpis:realtime:pending_orders', 300, $pending);
        Redis::setex('kpis:realtime:processing_orders', 300, $processing);
        Redis::setex('kpis:realtime:completed_today', 300, $completedToday);
        Redis::setex('kpis:realtime:failed_today', 300, $failedToday);
        Redis::setex('kpis:realtime:revenue_today', 300, $revenueToday);
    }
}

