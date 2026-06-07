<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'breaking_news',
        'daily_newsletter',
        'personalized_recommendations',
        'comment_replies',
        'saved_article_updates',

        'created_at',
        'updated_at',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
