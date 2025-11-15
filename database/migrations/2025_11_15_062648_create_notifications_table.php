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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('notification_type'); // 'success', 'failed'
            $table->string('channel'); // 'email', 'log'
            $table->string('recipient')->nullable(); // email address if email channel
            $table->string('order_reference'); // order_id string for easy lookup
            $table->unsignedBigInteger('customer_id');
            $table->string('order_status');
            $table->decimal('total_amount', 10, 2);
            $table->text('message');
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'notification_type']);
            $table->index(['customer_id']);
            $table->index(['status']);
            $table->index(['sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
