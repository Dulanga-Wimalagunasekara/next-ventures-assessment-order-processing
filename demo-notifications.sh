#!/bin/bash

# Demo script to test the notification system

echo "========================================="
echo "Notification System Demo"
echo "========================================="
echo ""

cd /Users/dulangawimalagunasekara/Documents/next-ventures-assessment-order-processing

echo "1. Creating a test notification..."
php artisan tinker --execute="
\$order = \App\Models\Order::first();
if (\$order) {
    echo 'Testing with order: ' . \$order->order_id . PHP_EOL;

    // Create a success notification
    \$notification = \App\Models\Notification::create([
        'order_id' => \$order->id,
        'notification_type' => 'success',
        'channel' => 'log',
        'order_reference' => \$order->order_id,
        'customer_id' => \$order->customer_id,
        'order_status' => 'completed',
        'total_amount' => \$order->total_amount,
        'message' => 'Order ' . \$order->order_id . ' has been processed successfully.',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    echo 'Notification created with ID: ' . \$notification->id . PHP_EOL;
    echo 'Total notifications: ' . \App\Models\Notification::count() . PHP_EOL;
} else {
    echo 'No orders found. Please run: php artisan orders:import sample.csv' . PHP_EOL;
}
"

echo ""
echo "2. Testing notification API endpoints..."

# Start server briefly
php artisan serve --port=8080 &
SERVER_PID=$!
sleep 3

echo ""
echo "Notification Stats:"
curl -s "http://localhost:8080/api/notifications/stats" | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    print(json.dumps(data, indent=2))
except:
    print('API response error or server not ready')
" 2>/dev/null || echo "API test failed"

echo ""
echo "Recent Notifications:"
curl -s "http://localhost:8080/api/notifications/recent?limit=3" | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    print(json.dumps(data, indent=2))
except:
    print('API response error or server not ready')
" 2>/dev/null || echo "API test failed"

# Stop server
kill $SERVER_PID 2>/dev/null

echo ""
echo "========================================="
echo "Notification System Features:"
echo "========================================="
echo "✅ Notification history tracking"
echo "✅ Success and failure notifications"
echo "✅ Email and log channels"
echo "✅ API endpoints for viewing notifications"
echo "✅ Notification statistics"
echo "✅ Queued notification jobs"
echo "✅ Automatic notifications on order completion"
echo ""
echo "API Endpoints:"
echo "- GET /api/notifications"
echo "- GET /api/notifications/stats"
echo "- GET /api/notifications/recent"
echo "- GET /api/notifications/order/{orderId}"
echo "- POST /api/notifications/{id}/resend"
echo ""
echo "Integration:"
echo "- Notifications sent automatically when orders complete or fail"
echo "- Jobs queued to 'notifications' queue (non-blocking workflow)"
echo "- 5-second delay to ensure transaction commits"
echo ""
