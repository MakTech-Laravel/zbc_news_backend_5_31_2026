<?php

namespace App\Support;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class ArticleAuditLogger
{
    public static function log(
        Article $article,
        string $event,
        string $description,
        ?User $causer = null,
        array $properties = [],
    ): void {
        $logger = activity()
            ->performedOn($article)
            ->event($event)
            ->withProperties(array_merge([
                'article_title' => $article->title,
                'article_slug' => $article->slug,
                'status' => $article->status instanceof ArticleStatus
                    ? $article->status->value
                    : (string) $article->status,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ], $properties));

        if ($causer) {
            $logger->causedBy($causer);
        }

        $logger->log($description);
    }

    /**
     * Map status transitions to discrete newsroom audit events.
     *
     * @return list<array{event: string, description: string}>
     */
    public static function statusTransitionEvents(?string $previous, string $next): array
    {
        $previous = $previous !== null ? strtolower($previous) : null;
        $next = strtolower($next);

        if ($previous === $next) {
            return [];
        }

        $events = [];

        if ($next === ArticleStatus::PENDING->value) {
            $events[] = [
                'event' => 'submitted_for_review',
                'description' => 'Article submitted for review',
            ];
        }

        if (
            $previous === ArticleStatus::PENDING->value
            && $next === ArticleStatus::PUBLISHED->value
        ) {
            $events[] = [
                'event' => 'approved',
                'description' => 'Article approved',
            ];
        }

        if (
            $previous === ArticleStatus::PENDING->value
            && in_array($next, [ArticleStatus::DRAFT->value, ArticleStatus::ARCHIVED->value], true)
        ) {
            $events[] = [
                'event' => 'rejected',
                'description' => 'Article rejected',
            ];
        }

        if ($next === ArticleStatus::PUBLISHED->value) {
            $events[] = [
                'event' => 'published',
                'description' => 'Article published',
            ];
        }

        if ($next === ArticleStatus::SCHEDULED->value) {
            $events[] = [
                'event' => 'scheduled',
                'description' => 'Article scheduled',
            ];
        }

        if ($next === ArticleStatus::ARCHIVED->value) {
            $events[] = [
                'event' => 'archived',
                'description' => 'Article archived',
            ];
        }

        return $events;
    }
}
