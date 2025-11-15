<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRefund;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RefundController extends Controller
{
    /**
     * Create a new refund request
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string|exists:orders,order_id',
            'refund_amount' => 'required|numeric|min:0.01',
            'refund_type' => 'required|in:partial,full',
            'reason' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = Order::where('order_id', $request->input('order_id'))->first();

            // Validate order is eligible for refund
            if (!in_array($order->status, ['completed'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Order {$order->order_id} is not eligible for refund (status: {$order->status})",
                ], 400);
            }

            // Check refundable amount
            $refundAmount = (float) $request->input('refund_amount');
            if ($refundAmount > $order->refundable_amount) {
                return response()->json([
                    'success' => false,
                    'message' => "Refund amount {$refundAmount} exceeds refundable amount {$order->refundable_amount}",
                ], 400);
            }

            // Validate refund type vs amount
            $refundType = $request->input('refund_type');
            if ($refundType === 'full' && $refundAmount != $order->refundable_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Full refund amount must equal the refundable amount',
                ], 400);
            }

            DB::beginTransaction();

            // Create refund record
            $refund = Refund::create([
                'order_id' => $order->id,
                'refund_id' => 'REF-' . $order->order_id . '-' . Str::upper(Str::random(6)),
                'order_reference' => $order->order_id,
                'customer_id' => $order->customer_id,
                'refund_type' => $refundType,
                'refund_amount' => $refundAmount,
                'original_amount' => $order->total_amount,
                'reason' => $request->input('reason'),
                'description' => $request->input('description'),
                'payment_method' => $request->input('payment_method', 'original_payment'),
                'status' => 'pending',
                'requested_at' => now(),
                'metadata' => [
                    'requested_by_api' => true,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ],
            ]);

            DB::commit();

            // Queue refund processing job (idempotent)
            ProcessRefund::dispatch($refund->id)->onQueue('refunds');

            return response()->json([
                'success' => true,
                'message' => 'Refund request created and queued for processing',
                'data' => [
                    'refund_id' => $refund->refund_id,
                    'order_id' => $order->order_id,
                    'refund_amount' => $refund->refund_amount,
                    'refund_type' => $refund->refund_type,
                    'status' => $refund->status,
                    'requested_at' => $refund->requested_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create refund request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all refunds with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = Refund::with('order:id,order_id,customer_name,status,total_amount');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by refund type
        if ($request->has('type')) {
            $query->where('refund_type', $request->input('type'));
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        // Filter by order
        if ($request->has('order_id')) {
            $query->where('order_reference', $request->input('order_id'));
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('requested_at', '>=', $request->input('from_date'));
        }

        if ($request->has('to_date')) {
            $query->whereDate('requested_at', '<=', $request->input('to_date'));
        }

        $refunds = $query->orderBy('requested_at', 'desc')
            ->paginate($request->input('limit', 15));

        return response()->json([
            'success' => true,
            'data' => $refunds,
        ]);
    }

    /**
     * Get specific refund details
     */
    public function show(string $refundId): JsonResponse
    {
        $refund = Refund::where('refund_id', $refundId)
            ->with(['order:id,order_id,customer_name,status,total_amount'])
            ->first();

        if (!$refund) {
            return response()->json([
                'success' => false,
                'message' => 'Refund not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $refund,
        ]);
    }

    /**
     * Get refunds for specific order
     */
    public function byOrder(string $orderId): JsonResponse
    {
        $refunds = Refund::where('order_reference', $orderId)
            ->with('order:id,order_id,customer_name,status,total_amount')
            ->orderBy('requested_at', 'desc')
            ->get();

        $order = Order::where('order_id', $orderId)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'order' => $order ? [
                    'total_amount' => $order->total_amount,
                    'total_refunded' => $order->total_refunded,
                    'refundable_amount' => $order->refundable_amount,
                    'is_fully_refunded' => $order->isFullyRefunded(),
                ] : null,
                'refunds' => $refunds,
            ],
        ]);
    }

    /**
     * Cancel a pending refund
     */
    public function cancel(string $refundId): JsonResponse
    {
        $refund = Refund::where('refund_id', $refundId)->first();

        if (!$refund) {
            return response()->json([
                'success' => false,
                'message' => 'Refund not found',
            ], 404);
        }

        if ($refund->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Cannot cancel refund in status: {$refund->status}",
            ], 400);
        }

        $refund->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Refund cancelled successfully',
            'data' => $refund,
        ]);
    }

    /**
     * Retry failed refund
     */
    public function retry(string $refundId): JsonResponse
    {
        $refund = Refund::where('refund_id', $refundId)->first();

        if (!$refund) {
            return response()->json([
                'success' => false,
                'message' => 'Refund not found',
            ], 404);
        }

        if ($refund->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => "Cannot retry refund in status: {$refund->status}",
            ], 400);
        }

        // Reset refund to pending
        $refund->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        // Re-queue processing
        ProcessRefund::dispatch($refund->id)->onQueue('refunds');

        return response()->json([
            'success' => true,
            'message' => 'Refund queued for retry',
            'data' => $refund,
        ]);
    }

    /**
     * Get refund statistics
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_refunds' => Refund::count(),
            'completed_refunds' => Refund::completed()->count(),
            'pending_refunds' => Refund::pending()->count(),
            'failed_refunds' => Refund::failed()->count(),
            'partial_refunds' => Refund::partial()->count(),
            'full_refunds' => Refund::full()->count(),
            'total_refund_amount' => Refund::completed()->sum('refund_amount'),
            'average_refund_amount' => Refund::completed()->avg('refund_amount') ?: 0,
            'today' => [
                'refunds' => Refund::whereDate('requested_at', today())->count(),
                'completed' => Refund::completed()->whereDate('processed_at', today())->count(),
                'amount' => Refund::completed()->whereDate('processed_at', today())->sum('refund_amount'),
            ],
            'by_type' => [
                'partial_amount' => Refund::partial()->completed()->sum('refund_amount'),
                'full_amount' => Refund::full()->completed()->sum('refund_amount'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
