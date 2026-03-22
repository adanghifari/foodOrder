<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MenuItem extends Model
{
    protected $collection = 'menu_item';

    protected $primaryKey = '_id';

    protected $fillable = [
        'name',
        'description',
        'price',
        'category',
        'image_url',
    ];
}
