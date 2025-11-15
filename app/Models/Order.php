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
}

