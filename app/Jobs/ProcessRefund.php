<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Refund;
use App\Services\KpiService;
use App\Services\LeaderboardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessRefund implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 120;

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
        $refund = Refund::find($this->refundId);

        if (!$refund) {
            Log::error("ProcessRefund: Refund not found", ['refund_id' => $this->refundId]);
            return;
        }

        // Idempotency check - if already completed, skip
        if ($refund->status === 'completed') {
            Log::info("ProcessRefund: Already completed", ['refund_id' => $refund->refund_id]);
            return;
        }

        Log::info("Processing refund: {$refund->refund_id}");

        DB::beginTransaction();
        try {
            // Validate refund is still valid
            $order = $refund->order;
            if (!$order) {
                throw new \Exception("Order not found for refund {$refund->refund_id}");
            }

            // Check if order is in valid state for refund
            if (!in_array($order->status, ['completed'])) {
                throw new \Exception("Order {$order->order_id} is not eligible for refund (status: {$order->status})");
            }

            // Validate refund amount doesn't exceed remaining refundable amount
            $totalRefunded = $order->refunds()->completed()
                ->where('id', '!=', $refund->id) // Exclude current refund for idempotency
                ->sum('refund_amount');

            $remainingRefundable = $order->total_amount - $totalRefunded;

            if ($refund->refund_amount > $remainingRefundable) {
                throw new \Exception("Refund amount {$refund->refund_amount} exceeds remaining refundable amount {$remainingRefundable}");
            }

            // Update refund status to processing
            $refund->update(['status' => 'processing']);

            // Simulate refund processing (payment gateway call)
            $this->processRefundPayment($refund);

            // Mark as completed
            $refund->update([
                'status' => 'completed',
                'processed_at' => now(),
                'transaction_id' => 'REF-' . Str::upper(Str::random(12)),
            ]);

            DB::commit();

            // Update KPIs and leaderboard asynchronously
            $this->updateMetricsAfterRefund($refund);

            Log::info("Refund processed successfully: {$refund->refund_id}");

        } catch (\Exception $e) {
            DB::rollBack();

            $refund->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("Refund processing failed for {$refund->refund_id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Simulate refund payment processing
     */
    private function processRefundPayment(Refund $refund): void
    {
        // Simulate payment gateway processing time
        sleep(rand(1, 3));

        // Simulate 95% success rate for refunds
        if (rand(1, 100) <= 5) {
            throw new \Exception("Payment gateway declined the refund");
        }

        Log::info("Refund payment processed", [
            'refund_id' => $refund->refund_id,
            'amount' => $refund->refund_amount,
            'type' => $refund->refund_type,
        ]);
    }

    /**
     * Update KPIs and leaderboard after successful refund
     */
    private function updateMetricsAfterRefund(Refund $refund): void
    {
        // Update KPIs in real-time
        UpdateRefundKpis::dispatch($refund->id)->onQueue('kpis');

        // Update customer leaderboard (reduce customer spending)
        UpdateCustomerLeaderboardAfterRefund::dispatch($refund->id)->onQueue('leaderboard');

        Log::info("Metrics update jobs dispatched for refund: {$refund->refund_id}");
    }
}

