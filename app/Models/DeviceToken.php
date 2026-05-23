<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class DeviceToken extends Model
{
    protected $collection = 'device_tokens';

    protected $primaryKey = '_id';

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'last_seen_at',
    ];
}

