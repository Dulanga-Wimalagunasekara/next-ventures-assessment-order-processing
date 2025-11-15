<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'order_id',
        'refund_id',
        'order_reference',
        'customer_id',
        'refund_type',
        'refund_amount',
        'original_amount',
        'reason',
        'description',
        'status',
        'payment_method',
        'transaction_id',
        'error_message',
        'requested_at',
        'processed_at',
        'metadata',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope for pending refunds
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed refunds
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed refunds
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for partial refunds
     */
    public function scopePartial($query)
    {
        return $query->where('refund_type', 'partial');
    }

    /**
     * Scope for full refunds
     */
    public function scopeFull($query)
    {
        return $query->where('refund_type', 'full');
    }

    /**
     * Get refund percentage
     */
    public function getRefundPercentageAttribute(): float
    {
        if ($this->original_amount <= 0) {
            return 0;
        }
        return ($this->refund_amount / $this->original_amount) * 100;
    }

    /**
     * Check if refund is complete
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if refund is partial
     */
    public function isPartial(): bool
    {
        return $this->refund_type === 'partial';
    }

    /**
     * Check if refund is full
     */
    public function isFull(): bool
    {
        return $this->refund_type === 'full';
    }
}
