<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdSlot extends Model
{
    protected $fillable = [
        'slot_key',
        'name',
        'placement',
        'provider',
        'is_active',
        'google_ad_client',
        'google_ad_slot',
        'manual_image_url',
        'manual_click_url',
        'manual_html',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function events()
    {
        return $this->hasMany(AdSlotEvent::class);
    }
}

