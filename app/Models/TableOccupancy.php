<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class TableOccupancy extends Model
{
    protected $collection = 'table_occupancies';

    protected $primaryKey = '_id';

    protected $fillable = [
        'table_number',
        'order_id',
        'order_type',
        'source_type',
        'status',
        'customer_name',
        'customer_email',
        'display_id',
        'start_at',
        'end_at',
        'meta',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];
}
