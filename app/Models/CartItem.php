<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CartItem extends Model
{
    protected $collection = 'cart_item';

    protected $primaryKey = '_id';

    protected $fillable = [
        'customer_id',
        'menu_item_id',
        'quantity',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
