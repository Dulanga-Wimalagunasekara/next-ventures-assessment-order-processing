<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemController extends Controller
{
    /**
     * Get system health status
     */
    public function health(): JsonResponse
    {
        $status = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'queue' => $this->checkQueue(),
            ],
        ];

        $allHealthy = collect($status['services'])->every(fn($service) => $service['status'] === 'healthy');

        return response()->json($status, $allHealthy ? 200 : 503);
    }

    /**
     * Get system statistics
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'orders' => [
                'total' => Order::count(),
                'pending' => Order::where('status', 'pending')->count(),
                'processing' => Order::whereIn('status', ['reserved', 'payment_processing'])->count(),
                'completed' => Order::where('status', 'completed')->count(),
                'failed' => Order::whereIn('status', ['failed', 'rollback'])->count(),
            ],
            'today' => [
                'orders' => Order::whereDate('created_at', today())->count(),
                'completed' => Order::whereDate('created_at', today())->where('status', 'completed')->count(),
                'revenue' => Order::whereDate('created_at', today())->where('status', 'completed')->sum('total_amount'),
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Check database connection
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check Redis connection
     */
    private function checkRedis(): array
    {
        try {
            Redis::ping();
            return [
                'status' => 'healthy',
                'message' => 'Redis connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Redis connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue status
     */
    private function checkQueue(): array
    {
        try {
            $size = Redis::llen('queues:orders');
            return [
                'status' => 'healthy',
                'message' => 'Queue is operational',
                'queue_size' => $size,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Queue check failed: ' . $e->getMessage(),
            ];
        }
    }
}

