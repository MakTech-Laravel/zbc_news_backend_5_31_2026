<?php

namespace App\Support;

class ReadTime
{
    private const WORDS_PER_MINUTE = 200;

    /** Words-per-minute for content-based estimate shown on public cards/details. */
    private const ESTIMATED_WORDS_PER_MINUTE = 250;

    public static function fromHtml(?string $content): string
    {
        if (! $content || ! trim(strip_tags($content))) {
            return '1 min read';
        }

        $wordCount = str_word_count(strip_tags($content));
        $minutes = max(1, (int) round($wordCount / self::WORDS_PER_MINUTE));

        return "{$minutes} min read";
    }

    /**
     * Estimated reading time from article body: ≤250 words → 1 min,
     * otherwise ceil(words / 250).
     */
    public static function estimatedFromHtml(?string $content): string
    {
        if (! $content || ! trim(strip_tags($content))) {
            return '1 min read';
        }

        $wordCount = str_word_count(strip_tags($content));
        $minutes = max(1, (int) ceil($wordCount / self::ESTIMATED_WORDS_PER_MINUTE));

        return "{$minutes} min read";
    }

    public static function fromSeconds(int $seconds): string
    {
        if ($seconds <= 0) {
            return '1 min read';
        }

        $minutes = max(1, (int) ceil($seconds / 60));

        return "{$minutes} min read";
    }
}
