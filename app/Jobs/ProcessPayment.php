<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessPayment implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 120;

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

        Log::info("Processing payment for order: {$order->order_id}");

        DB::beginTransaction();
        try {
            // Create payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'status' => 'processing',
            ]);

            // Update order status
            $order->update(['status' => 'payment_processing']);

            DB::commit();

            // Simulate payment processing with callback
            $this->simulatePaymentProcessing($payment, $order);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Payment processing failed for order {$order->order_id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Simulate payment processing with async callback
     */
    private function simulatePaymentProcessing(Payment $payment, Order $order): void
    {
        // Simulate payment gateway delay (1-3 seconds)
        sleep(rand(1, 3));

        // Simulate 90% success rate
        $success = rand(1, 100) <= 90;

        DB::beginTransaction();
        try {
            if ($success) {
                $payment->update([
                    'status' => 'completed',
                    'transaction_id' => 'TXN-' . Str::upper(Str::random(16)),
                ]);

                Log::info("Payment completed for order: {$order->order_id}, Transaction: {$payment->transaction_id}");
            } else {
                $payment->update([
                    'status' => 'failed',
                    'error_message' => 'Payment declined by gateway',
                ]);

                Log::warning("Payment failed for order: {$order->order_id}");
                throw new \Exception("Payment failed for order {$order->order_id}");
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
