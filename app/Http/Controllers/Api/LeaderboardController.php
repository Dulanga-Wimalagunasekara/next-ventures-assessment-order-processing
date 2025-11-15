<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function __construct(
        private LeaderboardService $leaderboardService
    ) {}

    /**
     * Get customer leaderboard
     */
    public function customers(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);

        $leaderboard = $this->leaderboardService->generateCustomerLeaderboard($limit);

        return response()->json([
            'success' => true,
            'data' => $leaderboard,
        ]);
    }

    /**
     * Get customer rank
     */
    public function customerRank(int $customerId): JsonResponse
    {
        $rank = $this->leaderboardService->getCustomerRank($customerId);

        if (!$rank) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found in leaderboard',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $rank,
        ]);
    }

    /**
     * Get product leaderboard
     */
    public function products(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);

        $leaderboard = $this->leaderboardService->generateProductLeaderboard($limit);

        return response()->json([
            'success' => true,
            'data' => $leaderboard,
        ]);
    }

    /**
     * Rebuild customer leaderboard
     */
    public function rebuild(): JsonResponse
    {
        $leaderboard = $this->leaderboardService->rebuildCustomerLeaderboard(100);

        return response()->json([
            'success' => true,
            'message' => 'Leaderboard rebuilt successfully',
            'data' => array_slice($leaderboard, 0, 10),
        ]);
    }
}
