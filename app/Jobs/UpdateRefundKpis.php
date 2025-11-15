<?php

namespace App\Jobs;

use App\Models\Refund;
use App\Services\KpiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UpdateRefundKpis implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $refundId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $refund = Refund::with('order')->find($this->refundId);

        if (!$refund) {
            Log::error("UpdateRefundKpis: Refund not found", ['refund_id' => $this->refundId]);
            return;
        }

        Log::info("Updating KPIs for refund: {$refund->refund_id}");

        try {
            $this->updateDailyRefundKpis($refund);
            $this->updateRealTimeKpis($refund);
            $this->invalidateRelatedKpiCache($refund);

            Log::info("KPIs updated for refund: {$refund->refund_id}");

        } catch (\Exception $e) {
            Log::error("Failed to update KPIs for refund {$refund->refund_id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update daily refund KPIs
     */
    private function updateDailyRefundKpis(Refund $refund): void
    {
        $date = $refund->processed_at->format('Y-m-d');
        $cacheKey = "kpis:refunds:daily:{$date}";

        // Get current daily refund stats
        $refundStats = Redis::get($cacheKey);
        $stats = $refundStats ? json_decode($refundStats, true) : [
            'date' => $date,
            'total_refunds' => 0,
            'refund_count' => 0,
            'partial_refunds' => 0,
            'full_refunds' => 0,
            'average_refund' => 0,
        ];

        // Update stats
        $stats['total_refunds'] += $refund->refund_amount;
        $stats['refund_count'] += 1;

        if ($refund->refund_type === 'partial') {
            $stats['partial_refunds'] += 1;
        } else {
            $stats['full_refunds'] += 1;
        }

        $stats['average_refund'] = $stats['refund_count'] > 0
            ? $stats['total_refunds'] / $stats['refund_count']
            : 0;

        $stats['updated_at'] = now()->toIso8601String();

        // Cache for 24 hours
        Redis::setex($cacheKey, 86400, json_encode($stats));
    }

    /**
     * Update real-time refund KPIs
     */
    private function updateRealTimeKpis(Refund $refund): void
    {
        $today = now()->format('Y-m-d');

        // Update today's refund metrics
        Redis::incrbyfloat("kpis:realtime:refunds_today", $refund->refund_amount);
        Redis::incr("kpis:realtime:refund_count_today");

        if ($refund->refund_type === 'partial') {
            Redis::incr("kpis:realtime:partial_refunds_today");
        } else {
            Redis::incr("kpis:realtime:full_refunds_today");
        }

        // Set expiration for real-time metrics (5 minutes)
        Redis::expire("kpis:realtime:refunds_today", 300);
        Redis::expire("kpis:realtime:refund_count_today", 300);
        Redis::expire("kpis:realtime:partial_refunds_today", 300);
        Redis::expire("kpis:realtime:full_refunds_today", 300);
    }

    /**
     * Invalidate related KPI cache that might be affected by refunds
     */
    private function invalidateRelatedKpiCache(Refund $refund): void
    {
        $order = $refund->order;
        $orderDate = $order->order_date->format('Y-m-d');

        // Clear daily KPI cache for the order date (revenue will be affected)
        $kpiCacheKey = "kpis:daily:{$orderDate}";
        Redis::del($kpiCacheKey);

        // Clear range caches that might include this date
        $pattern = "kpis:range:*{$orderDate}*";
        $keys = Redis::keys($pattern);
        if (!empty($keys)) {
            Redis::del($keys);
        }

        Log::info("Invalidated KPI cache for refund", [
            'refund_id' => $refund->refund_id,
            'order_date' => $orderDate,
        ]);
    }
}
