<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ProcessOrderWorkflow implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 300;

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
            Log::error("Order not found: {$this->orderId}");
            return;
        }

        Log::info("Starting workflow for order: {$order->order_id}");

        // Chain the workflow: Reserve Stock -> Process Payment -> Finalize
        Bus::chain([
            new ReserveStock($order->id),
            new ProcessPayment($order->id),
            new FinalizeOrder($order->id),
        ])->catch(function (\Throwable $e) use ($order) {
            Log::error("Workflow failed for order {$order->order_id}: " . $e->getMessage());
            // Trigger rollback on failure
            RollbackOrder::dispatch($order->id)->onQueue('orders');
        })->onQueue('orders')->dispatch();
    }
}
