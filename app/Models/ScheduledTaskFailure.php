<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTaskFailure extends Model
{
    protected $fillable = [
        'task_key',
        'task_name',
        'task_type',
        'failed_job_uuid',
        'queue_connection',
        'exception_message',
        'exception_trace',
        'status',
        'occurrence_count',
        'failed_at',
        'last_notified_at',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'resolved_at' => 'datetime',
            'occurrence_count' => 'integer',
        ];
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['failed', 'rerun_queued'], true);
    }
}
