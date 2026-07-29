<?php

namespace App\Models;

use App\Enums\CareerApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerApplication extends Model
{
    protected $fillable = [
        'career_job_id',
        'name',
        'email',
        'phone',
        'cover_letter',
        'resume_path',
        'resume_original_name',
        'resume_mime',
        'resume_size',
        'status',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'status' => CareerApplicationStatus::class,
            'resume_size' => 'integer',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(CareerJob::class, 'career_job_id');
    }
}
