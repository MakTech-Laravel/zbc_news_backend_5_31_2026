<?php

namespace App\Models;

use App\Enums\MenuRenderStyle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuLocation extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'render_style',
        'menu_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'render_style' => MenuRenderStyle::class,
        'is_active' => 'boolean',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
