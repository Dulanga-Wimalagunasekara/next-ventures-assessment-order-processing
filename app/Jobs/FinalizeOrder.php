<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\StockReservation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinalizeOrder implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $orderId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (!$order) {
            throw new \Exception("Order not found: {$this->orderId}");
        }

        Log::info("Finalizing order: {$order->order_id}");

        DB::beginTransaction();
        try {
            // Verify payment is completed
            $payment = $order->payment;
            if (!$payment || $payment->status !== 'completed') {
                throw new \Exception("Payment not completed for order {$order->order_id}");
            }

            // Update stock reservations to committed
            StockReservation::where('order_id', $order->id)
                ->where('status', 'reserved')
                ->update(['status' => 'committed']);

            // Update order status to completed
            $order->update(['status' => 'completed']);

            DB::commit();
            Log::info("Order finalized successfully: {$order->order_id}");

            // Queue success notification
            SendOrderNotification::dispatch($order->id, 'success', 'log')
                ->onQueue('notifications')
                ->delay(now()->addSeconds(5)); // Small delay to ensure transaction is committed

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Order finalization failed for {$order->order_id}: " . $e->getMessage());
            throw $e;
        }
    }
}
