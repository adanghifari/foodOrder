<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $collection = 'login_attempts';

    protected $primaryKey = '_id';

    protected $fillable = [
        'key',
        'attempts',
        'lockout_expires_at',
        'last_attempt_at',
    ];

    protected $casts = [
        'lockout_expires_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];
}
