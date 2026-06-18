<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthOtpCode extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'email',
        'purpose',
        'code',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
