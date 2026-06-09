<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;

class PermissionService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    
    public function getAllPermissions()
    {
        return Permission::all();
    }
}
