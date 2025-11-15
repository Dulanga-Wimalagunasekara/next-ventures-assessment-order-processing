<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderNotification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $orderId,
        public string $notificationType, // 'success' or 'failed'
        public string $channel = 'log', // 'email' or 'log'
        public ?string $recipient = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (!$order) {
            Log::error("SendOrderNotification: Order not found", ['order_id' => $this->orderId]);
            return;
        }

        // Create notification record
        $notification = Notification::create([
            'order_id' => $order->id,
            'notification_type' => $this->notificationType,
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'order_reference' => $order->order_id,
            'customer_id' => $order->customer_id,
            'order_status' => $order->status,
            'total_amount' => $order->total_amount,
            'message' => $this->buildMessage($order),
            'status' => 'pending',
        ]);

        try {
            if ($this->channel === 'email' && $this->recipient) {
                $this->sendEmailNotification($order, $notification);
            } else {
                $this->sendLogNotification($order, $notification);
            }

            // Mark as sent
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info("Order notification sent", [
                'order_id' => $order->order_id,
                'notification_type' => $this->notificationType,
                'channel' => $this->channel,
            ]);

        } catch (\Exception $e) {
            // Mark as failed
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("Failed to send order notification", [
                'order_id' => $order->order_id,
                'notification_type' => $this->notificationType,
                'channel' => $this->channel,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Let the job retry
        }
    }

    /**
     * Build notification message
     */
    private function buildMessage(Order $order): string
    {
        if ($this->notificationType === 'success') {
            return "Order {$order->order_id} has been processed successfully. " .
                   "Customer: {$order->customer_name} (ID: {$order->customer_id}), " .
                   "Total: {$order->currency} {$order->total_amount}, " .
                   "Status: {$order->status}";
        } else {
            return "Order {$order->order_id} processing failed. " .
                   "Customer: {$order->customer_name} (ID: {$order->customer_id}), " .
                   "Total: {$order->currency} {$order->total_amount}, " .
                   "Status: {$order->status}";
        }
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(Order $order, Notification $notification): void
    {
        $subject = $this->notificationType === 'success'
            ? "Order {$order->order_id} - Processing Complete"
            : "Order {$order->order_id} - Processing Failed";

        Mail::raw($notification->message, function ($message) use ($subject) {
            $message->to($this->recipient)
                    ->subject($subject)
                    ->from(config('mail.from.address', 'noreply@example.com'));
        });
    }

    /**
     * Send log notification
     */
    private function sendLogNotification(Order $order, Notification $notification): void
    {
        $logLevel = $this->notificationType === 'success' ? 'info' : 'warning';

        Log::log($logLevel, "ORDER_NOTIFICATION: {$notification->message}", [
            'order_id' => $order->order_id,
            'customer_id' => $order->customer_id,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'notification_type' => $this->notificationType,
            'notification_id' => $notification->id,
        ]);
    }
}

