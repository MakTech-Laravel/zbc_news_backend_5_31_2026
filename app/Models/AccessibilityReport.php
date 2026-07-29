<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessibilityReport extends Model
{
    protected $fillable = [
        'issue',
        'page_url',
        'email',
        'status',
        'ip_address',
        'user_agent',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
