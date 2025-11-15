<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockReservation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RollbackOrder implements ShouldQueue
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

        Log::info("Rolling back order: {$order->order_id}");

        DB::beginTransaction();
        try {
            // Find all stock reservations for this order
            $reservations = StockReservation::where('order_id', $order->id)
                ->where('status', 'reserved')
                ->get();

            foreach ($reservations as $reservation) {
                // Return stock to inventory
                $product = Product::where('sku', $reservation->product_sku)->first();
                if ($product) {
                    $product->increment('stock_quantity', $reservation->quantity);
                }

                // Mark reservation as released
                $reservation->update(['status' => 'released']);
            }

            // Update order status
            $order->update(['status' => 'rollback']);

            DB::commit();
            Log::info("Order rolled back successfully: {$order->order_id}");

            // Queue failure notification
            SendOrderNotification::dispatch($order->id, 'failed', 'log')
                ->onQueue('notifications')
                ->delay(now()->addSeconds(5)); // Small delay to ensure transaction is committed

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Rollback failed for order {$order->order_id}: " . $e->getMessage());
            throw $e;
        }
    }
}
