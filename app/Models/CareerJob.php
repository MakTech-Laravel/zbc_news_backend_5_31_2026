<?php

namespace App\Models;

use App\Enums\CareerEmploymentType;
use App\Enums\CareerJobDepartment;
use App\Enums\CareerJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CareerJob extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'department',
        'employment_type',
        'location',
        'description',
        'status',
        'sort_order',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'department' => CareerJobDepartment::class,
            'employment_type' => CareerEmploymentType::class,
            'status' => CareerJobStatus::class,
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CareerApplication::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', CareerJobStatus::PUBLISHED);
    }
}
