<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOrderWorkflow;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class ImportOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:import {file : The CSV file path to import}';

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

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Starting import from: {$filePath}");

        try {
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);

            $records = $csv->getRecords();
            $count = 0;
            $bar = $this->output->createProgressBar();
            $bar->start();

            foreach ($records as $record) {
                $totalAmount = (float)$record['quantity'] * (float)$record['unit_price'];

                $order = Order::create([
                    'order_id' => $record['order_id'],
                    'customer_id' => $record['customer_id'],
                    'customer_name' => $record['customer_name'],
                    'product_sku' => $record['product_sku'],
                    'product_name' => $record['product_name'],
                    'quantity' => $record['quantity'],
                    'unit_price' => $record['unit_price'],
                    'currency' => $record['currency'],
                    'order_date' => $record['order_date'],
                    'status' => 'pending',
                    'total_amount' => $totalAmount,
                ]);

                // Dispatch the order processing workflow job
                ProcessOrderWorkflow::dispatch($order->id)->onQueue('orders');

                $count++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("Successfully imported and queued {$count} orders for processing.");

            return 0;
        } catch (\Exception $e) {
            $this->error("Error importing orders: " . $e->getMessage());
            return 1;
        }
    }
}
