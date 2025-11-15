<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOrderWorkflow;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

class ImportOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:import {file : The CSV file path to import} {--force : Re-import and re-queue existing orders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import orders from a CSV file and queue them for processing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $force = (bool) $this->option('force');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Starting import from: {$filePath}");

        try {
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);

            $records = $csv->getRecords();
            $imported = 0;
            $skipped = 0;
            $queued = 0;

            $bar = $this->output->createProgressBar();
            $bar->start();

            foreach ($records as $record) {
                try {
                    // Normalize fields
                    $orderId = (string) ($record['order_id'] ?? '');
                    if ($orderId === '') {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    $existing = Order::where('order_id', $orderId)->first();

                    if ($existing && !$force) {
                        // Skip duplicates by default
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    // Calculate totals
                    $quantity = (int) ($record['quantity'] ?? 0);
                    $unitPrice = (float) ($record['unit_price'] ?? 0);
                    $totalAmount = $quantity * $unitPrice;

                    // Create or update
                    $order = Order::updateOrCreate(
                        ['order_id' => $orderId],
                        [
                            'customer_id' => (int) $record['customer_id'],
                            'customer_name' => (string) $record['customer_name'],
                            'product_sku' => (string) $record['product_sku'],
                            'product_name' => (string) $record['product_name'],
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'currency' => (string) ($record['currency'] ?? 'USD'),
                            'order_date' => (string) $record['order_date'],
                            // When forcing, reset status to pending to re-run workflow; otherwise new records start pending
                            'status' => 'pending',
                            'total_amount' => $totalAmount,
                        ]
                    );

                    $imported++;

                    // Queue workflow if newly created or forced
                    if ($order->wasRecentlyCreated || $force) {
                        ProcessOrderWorkflow::dispatch($order->id)->onQueue('orders');
                        $queued++;
                    }
                } catch (\Throwable $e) {
                    // Log and continue with next record
                    Log::warning('ImportOrders: failed row', [
                        'error' => $e->getMessage(),
                        'row' => $record,
                    ]);
                    $skipped++;
                } finally {
                    $bar->advance();
                }
            }

            $bar->finish();
            $this->newLine();
            $this->info("Imported: {$imported}, Skipped: {$skipped}, Queued: {$queued}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Error importing orders: " . $e->getMessage());
            return 1;
        }
    }
}
