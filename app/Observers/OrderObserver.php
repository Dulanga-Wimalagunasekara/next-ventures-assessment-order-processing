<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\LeaderboardService;
use App\Services\KpiService;

class OrderObserver
{
    public function __construct(
        private LeaderboardService $leaderboardService,
        private KpiService $kpiService
    ) {}

    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Update real-time KPIs when order is created
        $this->kpiService->updateRealTimeKpis();
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Update leaderboard when order is completed
        if ($order->wasChanged('status') && $order->status === 'completed') {
            $this->leaderboardService->updateCustomerScore(
                $order->customer_id,
                $order->customer_name
            );

            // Update real-time KPIs
            $this->kpiService->updateRealTimeKpis();
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}

