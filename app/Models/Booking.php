<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class Booking extends Model
{
    protected $collection = 'bookings';

    protected $primaryKey = '_id';

    protected $fillable = [
        'customer_id',
        'table_number',
        'booking_start_at',
        'booking_end_at',
        'duration_hours',
        'extra_charge',
        'total_booking_charge',
        'status',
        'notes',
        'checked_in_at',
        'completed_at',
        'canceled_at',
    ];

    protected $casts = [
        'booking_start_at' => 'datetime',
        'booking_end_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'completed_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id', '_id');
    }
}
