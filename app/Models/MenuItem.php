<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $table = 'menu_items';

    protected $primaryKey = 'id';

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
        'tags',
        'spice_level',
        'sweet_level',
        'fresh_level',
        'calorie_level',
        'recommendation_note',
    ];
}
