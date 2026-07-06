<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactInquiryReply extends Model
{
    protected $fillable = [
        'contact_inquiry_id',
        'user_id',
        'subject',
        'body',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(ContactInquiry::class, 'contact_inquiry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
