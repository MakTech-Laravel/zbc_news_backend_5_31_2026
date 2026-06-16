<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdSlotEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ad_slot_id',
        'event_type',
        'revenue_cents',
        'session_id',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'revenue_cents' => 'integer',
        'created_at' => 'datetime',
    ];

    public function adSlot(): BelongsTo
    {
        return $this->belongsTo(AdSlot::class);
    }
}
