<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'order_id',
        'notification_type',
        'channel',
        'recipient',
        'order_reference',
        'customer_id',
        'order_status',
        'total_amount',
        'message',
        'sent_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope for successful notifications
     */
    public function scopeSuccessNotifications($query)
    {
        return $query->where('notification_type', 'success');
    }

    /**
     * Scope for failed notifications
     */
    public function scopeFailedNotifications($query)
    {
        return $query->where('notification_type', 'failed');
    }

    /**
     * Scope for sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }
}
