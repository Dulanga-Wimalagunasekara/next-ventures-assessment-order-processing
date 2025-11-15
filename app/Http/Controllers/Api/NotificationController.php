<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get all notifications with optional filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::with('order:id,order_id,customer_name,status');

        // Filter by notification type
        if ($request->has('type')) {
            $query->where('notification_type', $request->input('type'));
        }

        // Filter by channel
        if ($request->has('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('limit', 15));

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Get notifications for a specific order
     */
    public function byOrder(string $orderId): JsonResponse
    {
        $notifications = Notification::where('order_reference', $orderId)
            ->with('order:id,order_id,customer_name,status')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Get notification statistics
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_notifications' => Notification::count(),
            'success_notifications' => Notification::successNotifications()->count(),
            'failed_notifications' => Notification::failedNotifications()->count(),
            'sent_notifications' => Notification::sent()->count(),
            'pending_notifications' => Notification::where('status', 'pending')->count(),
            'failed_sends' => Notification::where('status', 'failed')->count(),
            'by_channel' => [
                'email' => Notification::where('channel', 'email')->count(),
                'log' => Notification::where('channel', 'log')->count(),
            ],
            'today' => [
                'total' => Notification::whereDate('created_at', today())->count(),
                'success' => Notification::successNotifications()
                    ->whereDate('created_at', today())->count(),
                'failed' => Notification::failedNotifications()
                    ->whereDate('created_at', today())->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get recent notifications
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 20);

        $notifications = Notification::with('order:id,order_id,customer_name,status')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Resend a failed notification
     */
    public function resend(int $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);

        if ($notification->status === 'sent') {
            return response()->json([
                'success' => false,
                'message' => 'Notification was already sent successfully',
            ], 400);
        }

        // Reset notification status
        $notification->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        // Re-queue the notification
        \App\Jobs\SendOrderNotification::dispatch(
            $notification->order_id,
            $notification->notification_type,
            $notification->channel,
            $notification->recipient
        )->onQueue('notifications');

        return response()->json([
            'success' => true,
            'message' => 'Notification has been re-queued for sending',
        ]);
    }
}
