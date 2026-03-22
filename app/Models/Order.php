<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Order extends Model
{
    protected $collection = 'orders';

    protected $primaryKey = '_id';

    protected $fillable = [
        'customer_id',
        'table_number',
        'payment_status',
        'queue_number',
        'status',
        'total_price',
        'items', // Array of embedded items
    ];
}
