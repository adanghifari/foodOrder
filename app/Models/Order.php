<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class Order extends Model
{
    protected $collection = 'orders';

    protected $primaryKey = '_id';

    protected $fillable = [
        'customer_id',
        'customer_name',
        'customer_email',
        'browser_session_id',
        'order_type',
        'table_number',
        'booking_start_at',
        'duration_hours',
        'payment_status',
        'midtrans_order_id',
        'payment_type',
        'payment_url',
        'payment_payload',
        'paid_at',
        'receipt_email_sent_at',
        'receipt_email_error',
        'stock_reserved_at',
        'stock_restored_at',
        'delivered_at',
        'table_cleared_at',
        'order_deleted_at',
        'queue_number',
        'status',
        'total_price',
        'service_fee',
        'extra_charge',
        'items', // Array of embedded items
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id', '_id');
    }
}
