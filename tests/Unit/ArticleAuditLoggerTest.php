<?php

namespace Tests\Unit;

use App\Enums\ArticleStatus;
use App\Support\ArticleAuditLogger;
use PHPUnit\Framework\TestCase;

class ArticleAuditLoggerTest extends TestCase
{
    public function test_status_transition_events_cover_newsroom_actions(): void
    {
        $this->assertSame(
            [['event' => 'submitted_for_review', 'description' => 'Article submitted for review']],
            ArticleAuditLogger::statusTransitionEvents(ArticleStatus::DRAFT->value, ArticleStatus::PENDING->value),
        );

        $approved = ArticleAuditLogger::statusTransitionEvents(
            ArticleStatus::PENDING->value,
            ArticleStatus::PUBLISHED->value,
        );
        $this->assertSame(['approved', 'published'], array_column($approved, 'event'));

        $rejected = ArticleAuditLogger::statusTransitionEvents(
            ArticleStatus::PENDING->value,
            ArticleStatus::DRAFT->value,
        );
        $this->assertSame(['rejected'], array_column($rejected, 'event'));

        $this->assertSame(
            [['event' => 'scheduled', 'description' => 'Article scheduled']],
            ArticleAuditLogger::statusTransitionEvents(ArticleStatus::DRAFT->value, ArticleStatus::SCHEDULED->value),
        );

        $this->assertSame(
            [['event' => 'archived', 'description' => 'Article archived']],
            ArticleAuditLogger::statusTransitionEvents(ArticleStatus::PUBLISHED->value, ArticleStatus::ARCHIVED->value),
        );

        $this->assertSame([], ArticleAuditLogger::statusTransitionEvents('draft', 'draft'));
    }
}
