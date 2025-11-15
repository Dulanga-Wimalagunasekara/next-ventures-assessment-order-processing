<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KpiController extends Controller
{
    public function __construct(
        private KpiService $kpiService
    ) {}

    /**
     * Get daily KPIs
     */
    public function daily(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        $kpis = $this->kpiService->generateDailyKpis($date);

        return response()->json([
            'success' => true,
            'data' => $kpis,
        ]);
    }

    /**
     * Get KPIs for a date range
     */
    public function range(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $kpis = $this->kpiService->getKpisForRange(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json([
            'success' => true,
            'data' => $kpis,
        ]);
    }

    /**
     * Get real-time KPIs
     */
    public function realtime(): JsonResponse
    {
        $this->kpiService->updateRealTimeKpis();
        $kpis = $this->kpiService->getRealTimeKpis();

        return response()->json([
            'success' => true,
            'data' => $kpis,
        ]);
    }
}

