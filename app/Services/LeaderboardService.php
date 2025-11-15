<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const LEADERBOARD_SIZE = 100;

    /**
     * Generate customer leaderboard based on total spending
     */
    public function generateCustomerLeaderboard(int $limit = 10): array
    {
        $cacheKey = "leaderboard:customers:spending";

        // Try to get from Redis sorted set
        $leaderboard = Redis::zrevrange($cacheKey, 0, $limit - 1, 'WITHSCORES');

        if (!empty($leaderboard)) {
            return $this->formatLeaderboard($leaderboard);
        }

        // Generate leaderboard from database
        return $this->rebuildCustomerLeaderboard($limit);
    }

    /**
     * Rebuild customer leaderboard from database
     */
    public function rebuildCustomerLeaderboard(int $limit = 10): array
    {
        $cacheKey = "leaderboard:customers:spending";

        // Get top customers by total spending
        $topCustomers = Order::select('customer_id', 'customer_name')
            ->selectRaw('SUM(total_amount) as total_spent')
            ->selectRaw('COUNT(*) as order_count')
            ->where('status', 'completed')
            ->groupBy('customer_id', 'customer_name')
            ->orderByDesc('total_spent')
            ->limit(self::LEADERBOARD_SIZE)
            ->get();

        // Clear existing leaderboard
        Redis::del($cacheKey);

        // Populate Redis sorted set
        foreach ($topCustomers as $customer) {
            $memberData = json_encode([
                'customer_id' => $customer->customer_id,
                'customer_name' => $customer->customer_name,
                'order_count' => $customer->order_count,
            ]);

            Redis::zadd($cacheKey, $customer->total_spent, $memberData);
        }

        // Set expiration
        Redis::expire($cacheKey, self::CACHE_TTL);

        // Get top N
        $leaderboard = Redis::zrevrange($cacheKey, 0, $limit - 1, 'WITHSCORES');

        return $this->formatLeaderboard($leaderboard);
    }

    /**
     * Update customer score in leaderboard
     */
    public function updateCustomerScore(int $customerId, string $customerName): void
    {
        $cacheKey = "leaderboard:customers:spending";

        // Calculate total spent
        $stats = Order::where('customer_id', $customerId)
            ->where('status', 'completed')
            ->selectRaw('SUM(total_amount) as total_spent')
            ->selectRaw('COUNT(*) as order_count')
            ->first();

        if ($stats && $stats->total_spent > 0) {
            $memberData = json_encode([
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'order_count' => $stats->order_count,
            ]);

            Redis::zadd($cacheKey, $stats->total_spent, $memberData);
            Redis::expire($cacheKey, self::CACHE_TTL);

            // Trim to keep only top N
            Redis::zremrangebyrank($cacheKey, 0, -(self::LEADERBOARD_SIZE + 1));
        }
    }

    /**
     * Get customer rank in leaderboard
     */
    public function getCustomerRank(int $customerId): ?array
    {
        $cacheKey = "leaderboard:customers:spending";

        // Find customer in sorted set
        $members = Redis::zrevrange($cacheKey, 0, -1, 'WITHSCORES');

        $rank = 1;
        foreach ($members as $index => $member) {
            if ($index % 2 === 0) { // Keys are at even indices
                $data = json_decode($member, true);
                if ($data['customer_id'] == $customerId) {
                    return [
                        'rank' => $rank,
                        'customer_id' => $data['customer_id'],
                        'customer_name' => $data['customer_name'],
                        'order_count' => $data['order_count'],
                        'total_spent' => $members[$index + 1],
                    ];
                }
                $rank++;
            }
        }

        return null;
    }

    /**
     * Format leaderboard data
     */
    private function formatLeaderboard(array $leaderboard): array
    {
        $formatted = [];
        $rank = 1;

        for ($i = 0; $i < count($leaderboard); $i += 2) {
            $data = json_decode($leaderboard[$i], true);
            $score = $leaderboard[$i + 1];

            $formatted[] = [
                'rank' => $rank++,
                'customer_id' => $data['customer_id'],
                'customer_name' => $data['customer_name'],
                'order_count' => $data['order_count'],
                'total_spent' => (float)$score,
            ];
        }

        return $formatted;
    }

    /**
     * Get product leaderboard (best selling products)
     */
    public function generateProductLeaderboard(int $limit = 10): array
    {
        $cacheKey = "leaderboard:products:sales";

        $cached = Redis::get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        $topProducts = Order::select('product_sku', 'product_name')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(total_amount) as total_revenue')
            ->selectRaw('COUNT(*) as order_count')
            ->where('status', 'completed')
            ->groupBy('product_sku', 'product_name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get()
            ->map(function ($product, $index) {
                return [
                    'rank' => $index + 1,
                    'product_sku' => $product->product_sku,
                    'product_name' => $product->product_name,
                    'total_quantity' => $product->total_quantity,
                    'total_revenue' => (float)$product->total_revenue,
                    'order_count' => $product->order_count,
                ];
            })
            ->toArray();

        Redis::setex($cacheKey, self::CACHE_TTL, json_encode($topProducts));

        return $topProducts;
    }
}

