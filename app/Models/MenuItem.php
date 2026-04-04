<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MenuItem extends Model
{
    protected $collection = 'menu_item';

    protected $primaryKey = '_id';

    protected $attributes = [
        'stock' => 0,
    ];

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'category',
        'image_url',
    ];
}
