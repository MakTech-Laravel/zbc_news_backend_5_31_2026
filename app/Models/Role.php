<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'is_protected',
        'display_name',
    ];

    protected $casts = [
        'is_protected' => 'boolean',
    ];
}
