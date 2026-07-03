<?php

namespace App\Services\Newsletter;

class NewsletterContentFormatter
{
    /**
     * @return array{html: string, is_html: bool}
     */
    public function prepareBody(string $content): array
    {
        $content = trim($content);

        if ($content === '') {
            return [
                'html' => '<p style="margin:0;font-size:16px;line-height:1.7;color:#4b5563;">&nbsp;</p>',
                'is_html' => false,
            ];
        }

        $content = $this->extractBodyFragment($content);
        $isHtml = $this->appearsToBeHtml($content);

        return [
            'html' => $isHtml ? $content : $this->plainTextToHtml($content),
            'is_html' => $isHtml,
        ];
    }

    public function appearsToBeHtml(string $content): bool
    {
        $trimmed = trim($content);

        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^\s*<!DOCTYPE\b/i', $trimmed) || preg_match('/^\s*<html\b/i', $trimmed)) {
            return true;
        }

        return (bool) preg_match(
            '/<(?:p|div|h[1-6]|ul|ol|li|table|thead|tbody|tr|td|th|br|img|a|strong|b|em|i|span|blockquote|pre|code|hr)\b/i',
            $trimmed,
        );
    }

    public function plainTextToHtml(string $text): string
    {
        $paragraphs = preg_split("/\r\n|\r|\n/", trim($text)) ?: [];
        $chunks = [];
        $buffer = [];

        foreach ($paragraphs as $line) {
            if (trim($line) === '') {
                if ($buffer !== []) {
                    $chunks[] = implode("\n", $buffer);
                    $buffer = [];
                }

                continue;
            }

            $buffer[] = $line;
        }

        if ($buffer !== []) {
            $chunks[] = implode("\n", $buffer);
        }

        if ($chunks === []) {
            return '<p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#374151;">&nbsp;</p>';
        }

        $html = '';

        foreach ($chunks as $chunk) {
            $html .= '<p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#374151;">'
                . nl2br(e($chunk), false)
                . '</p>';
        }

        return rtrim($html);
    }

    private function extractBodyFragment(string $content): string
    {
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $content, $matches)) {
            return trim($matches[1]);
        }

        return $content;
    }
}
