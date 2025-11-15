<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_id',
        'customer_id',
        'customer_name',
        'product_sku',
        'product_name',
        'quantity',
        'unit_price',
        'currency',
        'order_date',
        'status',
        'total_amount',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function stockReservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Get total refunded amount for this order
     */
    public function getTotalRefundedAttribute(): float
    {
        return $this->refunds()->completed()->sum('refund_amount');
    }

    /**
     * Get remaining refundable amount
     */
    public function getRefundableAmountAttribute(): float
    {
        return max(0, $this->total_amount - $this->total_refunded);
    }

    /**
     * Check if order is fully refunded
     */
    public function isFullyRefunded(): bool
    {
        return $this->total_refunded >= $this->total_amount;
    }

    /**
     * Check if order has any refunds
     */
    public function hasRefunds(): bool
    {
        return $this->refunds()->completed()->exists();
    }
}

