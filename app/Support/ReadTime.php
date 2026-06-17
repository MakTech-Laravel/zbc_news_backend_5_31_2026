<?php

namespace App\Support;

class ReadTime
{
    private const WORDS_PER_MINUTE = 200;

    public static function fromHtml(?string $content): string
    {
        if (! $content || ! trim(strip_tags($content))) {
            return '1 min read';
        }

        $wordCount = str_word_count(strip_tags($content));
        $minutes   = max(1, (int) round($wordCount / self::WORDS_PER_MINUTE));

        return "{$minutes} min read";
    }
}
