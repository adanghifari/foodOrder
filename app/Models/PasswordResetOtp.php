<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class PasswordResetOtp extends Model
{
    protected $collection = 'password_reset_otps';

    protected $primaryKey = '_id';

    protected $fillable = [
        'email',
        'otp',
        'token',
        'expired_at',
        'is_verified',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'is_verified' => 'boolean',
    ];
}
