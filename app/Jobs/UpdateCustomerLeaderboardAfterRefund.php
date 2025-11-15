<?php

namespace App\Jobs;

use App\Models\Refund;
use App\Services\LeaderboardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UpdateCustomerLeaderboardAfterRefund implements ShouldQueue
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
            Log::error("UpdateCustomerLeaderboardAfterRefund: Refund not found", ['refund_id' => $this->refundId]);
            return;
        }

        Log::info("Updating customer leaderboard for refund: {$refund->refund_id}");

        try {
            $this->updateCustomerLeaderboard($refund);
            $this->invalidateLeaderboardCache($refund);

            Log::info("Customer leaderboard updated for refund: {$refund->refund_id}");

        } catch (\Exception $e) {
            Log::error("Failed to update leaderboard for refund {$refund->refund_id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update customer leaderboard by reducing customer's spending
     */
    private function updateCustomerLeaderboard(Refund $refund): void
    {
        $order = $refund->order;
        $cacheKey = "leaderboard:customers:spending";

        // Get current leaderboard
        $members = Redis::zrevrange($cacheKey, 0, -1, 'WITHSCORES');

        // Find and update customer
        $found = false;
        for ($i = 0; $i < count($members); $i += 2) {
            $memberData = json_decode($members[$i], true);

            if ($memberData['customer_id'] == $order->customer_id) {
                $found = true;
                $currentScore = (float)$members[$i + 1];
                $newScore = max(0, $currentScore - $refund->refund_amount); // Reduce by refund amount

                // Update the member data
                $memberData['total_spent'] = $newScore;

                // Remove old entry and add updated one
                Redis::zrem($cacheKey, $members[$i]);
                Redis::zadd($cacheKey, $newScore, json_encode($memberData));

                Log::info("Updated customer in leaderboard", [
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer_name,
                    'old_score' => $currentScore,
                    'new_score' => $newScore,
                    'refund_amount' => $refund->refund_amount,
                ]);

                break;
            }
        }

        if (!$found) {
            // Customer not in leaderboard, recalculate their total
            $leaderboardService = app(LeaderboardService::class);
            $leaderboardService->updateCustomerScore($order->customer_id, $order->customer_name);

            Log::info("Customer not found in leaderboard, recalculated total", [
                'customer_id' => $order->customer_id,
                'customer_name' => $order->customer_name,
            ]);
        }

        // Set expiration
        Redis::expire($cacheKey, 3600); // 1 hour
    }

    /**
     * Invalidate leaderboard-related cache
     */
    private function invalidateLeaderboardCache(Refund $refund): void
    {
        // Clear customer-specific rank cache if exists
        $customerRankKey = "leaderboard:customer:{$refund->order->customer_id}:rank";
        Redis::del($customerRankKey);

        Log::info("Invalidated leaderboard cache for refund", [
            'refund_id' => $refund->refund_id,
            'customer_id' => $refund->order->customer_id,
        ]);
    }
}
