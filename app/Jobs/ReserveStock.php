<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockReservation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReserveStock implements ShouldQueue
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

        Log::info("Reserving stock for order: {$order->order_id}");

        DB::beginTransaction();
        try {
            // Get or create product
            $product = Product::firstOrCreate(
                ['sku' => $order->product_sku],
                [
                    'name' => $order->product_name,
                    'price' => $order->unit_price,
                    'stock_quantity' => 1000, // Initial stock for new products
                ]
            );

            // Check stock availability
            if ($product->stock_quantity < $order->quantity) {
                throw new \Exception("Insufficient stock for product {$product->sku}. Available: {$product->stock_quantity}, Required: {$order->quantity}");
            }

            // Decrement stock
            $product->decrement('stock_quantity', $order->quantity);

            // Create stock reservation
            StockReservation::create([
                'order_id' => $order->id,
                'product_sku' => $order->product_sku,
                'quantity' => $order->quantity,
                'status' => 'reserved',
                'expires_at' => now()->addMinutes(15), // Reservation expires in 15 minutes
            ]);

            // Update order status
            $order->update(['status' => 'reserved']);

            DB::commit();
            Log::info("Stock reserved successfully for order: {$order->order_id}");
        } catch (\Exception $e) {
            DB::rollBack();
            $order->update(['status' => 'failed']);
            Log::error("Stock reservation failed for order {$order->order_id}: " . $e->getMessage());
            throw $e;
        }
    }
}
