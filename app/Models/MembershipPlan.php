<?php

namespace App\Models;

use App\Enums\DurationType;
use App\Enums\MembershipPlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlan extends Model
{

    use SoftDeletes;

    protected $fillable = [
        'id',
        'title',
        'sub_title',
        'price',
        'duration',
        'duration_type',
        'status',
        'featured',

        'created_at',
        'updated_at',
    ];
    
    protected $casts = [
        'duration_type' => DurationType::class,
        'status' => MembershipPlanStatus::class,
        'featured' => 'array',
    ];
}

