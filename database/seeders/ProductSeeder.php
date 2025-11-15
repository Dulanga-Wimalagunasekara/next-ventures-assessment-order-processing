<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            ['sku' => 'SKU-001', 'name' => 'Wireless Mouse', 'price' => 25.50, 'stock_quantity' => 1000],
            ['sku' => 'SKU-002', 'name' => 'USB-C Charger', 'price' => 15.00, 'stock_quantity' => 1500],
            ['sku' => 'SKU-003', 'name' => 'Mechanical Keyboard', 'price' => 79.99, 'stock_quantity' => 500],
            ['sku' => 'SKU-004', 'name' => 'HD Webcam', 'price' => 49.99, 'stock_quantity' => 750],
            ['sku' => 'SKU-005', 'name' => 'External SSD 1TB', 'price' => 129.99, 'stock_quantity' => 300],
            ['sku' => 'SKU-006', 'name' => 'Noise Cancelling Headphones', 'price' => 199.00, 'stock_quantity' => 400],
            ['sku' => 'SKU-007', 'name' => 'Portable Monitor', 'price' => 179.99, 'stock_quantity' => 200],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                $product
            );
        }

        $this->command->info('Products seeded successfully!');
    }
}

