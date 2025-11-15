<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->unsignedBigInteger('customer_id');
            $table->string('customer_name');
            $table->string('product_sku');
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('order_date');
            $table->enum('status', ['pending', 'reserved', 'payment_processing', 'completed', 'failed', 'rollback'])->default('pending');
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
            $table->index('order_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

