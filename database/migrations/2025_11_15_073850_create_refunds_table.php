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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('refund_id')->unique(); // External refund reference
            $table->string('order_reference'); // order_id string for easy lookup
            $table->unsignedBigInteger('customer_id');
            $table->enum('refund_type', ['partial', 'full']);
            $table->decimal('refund_amount', 10, 2);
            $table->decimal('original_amount', 10, 2); // Original order total
            $table->string('reason')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('payment_method')->nullable(); // How refund is processed
            $table->string('transaction_id')->nullable(); // External transaction reference
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable(); // Additional refund data
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['customer_id']);
            $table->index(['refund_id']);
            $table->index(['status']);
            $table->index(['requested_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
