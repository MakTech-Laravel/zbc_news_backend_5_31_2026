<?php

namespace App\Models;

use App\Enums\ContactInquiryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactInquiry extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'ip_address',
        'user_agent',
        'status',
        'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactInquiryStatus::class,
            'replied_at' => 'datetime',
        ];
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ContactInquiryReply::class)->latest();
    }
}
