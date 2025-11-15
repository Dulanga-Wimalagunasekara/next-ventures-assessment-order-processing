<?php

namespace App\Services;

use App\Jobs\SendOrderNotification;
use App\Models\Order;

class NotificationService
{
    /**
     * Send order success notification
     */
    public function sendOrderSuccess(Order $order, array $options = []): void
    {
        $channel = $options['channel'] ?? 'log';
        $recipient = $options['recipient'] ?? null;
        $delay = $options['delay'] ?? 5; // seconds

        SendOrderNotification::dispatch($order->id, 'success', $channel, $recipient)
            ->onQueue('notifications')
            ->delay(now()->addSeconds($delay));
    }

    /**
     * Send order failure notification
     */
    public function sendOrderFailure(Order $order, array $options = []): void
    {
        $channel = $options['channel'] ?? 'log';
        $recipient = $options['recipient'] ?? null;
        $delay = $options['delay'] ?? 5; // seconds

        SendOrderNotification::dispatch($order->id, 'failed', $channel, $recipient)
            ->onQueue('notifications')
            ->delay(now()->addSeconds($delay));
    }

    /**
     * Send email notification for order
     */
    public function sendOrderEmail(Order $order, string $type, string $email, int $delay = 5): void
    {
        SendOrderNotification::dispatch($order->id, $type, 'email', $email)
            ->onQueue('notifications')
            ->delay(now()->addSeconds($delay));
    }

    /**
     * Send immediate notification (no queue)
     */
    public function sendImmediate(Order $order, string $type, string $channel = 'log', ?string $recipient = null): void
    {
        $job = new SendOrderNotification($order->id, $type, $channel, $recipient);
        $job->handle();
    }

    /**
     * Get notification preferences for customer
     * This could be expanded to read from customer preferences table
     */
    public function getCustomerNotificationPreferences(int $customerId): array
    {
        // Default preferences - could be customized per customer
        return [
            'success' => [
                'channel' => 'log',
                'recipient' => null,
            ],
            'failed' => [
                'channel' => 'log',
                'recipient' => null,
            ],
        ];
    }

    /**
     * Send notifications based on customer preferences
     */
    public function sendBasedOnPreferences(Order $order, string $type): void
    {
        $preferences = $this->getCustomerNotificationPreferences($order->customer_id);

        if (isset($preferences[$type])) {
            $config = $preferences[$type];

            if ($type === 'success') {
                $this->sendOrderSuccess($order, $config);
            } else {
                $this->sendOrderFailure($order, $config);
            }
        }
    }
}
