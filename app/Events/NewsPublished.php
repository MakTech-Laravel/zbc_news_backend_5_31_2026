<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewsPublished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $articleId,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $category,
    ) {}

    public function broadcastOn(): array
    {
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
        return 'NewsPublished';
    }

    public function broadcastWith(): array
    {
        return [
            'id'       => $this->articleId,
            'title'    => $this->title,
            'slug'     => $this->slug,
            'category' => $this->category,
        ];
    }
}