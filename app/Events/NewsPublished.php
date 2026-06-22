<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NewsPublished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $articleId,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?string $category,
    ) {}

    public function broadcastOn(): array
    {
        Log::info('NewsPublished event broadcasted on channel', [
            'channel' => 'news-updates',
        ]);
        return [
            new Channel('news-updates'),
        ];
    }

    /**
     * Frontend listens as: channel.listen('.NewsPublished', callback)
     * The dot prefix is required when using broadcastAs()
     */
    public function broadcastAs(): string
    {
        Log::info('NewsPublished event broadcasted', [
            'articleId' => $this->articleId,
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->category,
        ]);
        return 'NewsPublished';
    }

    public function broadcastWith(): array
    {
        Log::info('NewsPublished event broadcasted with', [
            'id' => $this->articleId,
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->category,
        ]);
        return [
            'id'       => $this->articleId,
            'title'    => $this->title,
            'slug'     => $this->slug,
            'category' => $this->category,
        ];
    }
}
