<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class ArticleHtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'h2', 'h3', 'strong', 'b', 'em', 'i', 'u', 'a',
        'ul', 'ol', 'li', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'img', 'video', 'audio', 'figure', 'figcaption', 'div', 'iframe',
    ];

    /** @var list<string> */
    private const EDITOR_ONLY_ATTRS = [
        'data-editor-video-replace-id',
    ];

    /** @var list<string> */
    private const GLOBAL_ATTRS = [
        'class', 'style', 'contenteditable', 'data-embed-type', 'data-aspect-ratio', 'data-embed-orientation', 'data-embed-width', 'data-embed-height', 'data-caption', 'data-credit', 'data-copyright',
    ];

    /** @var list<string> */
    private const MEDIA_ATTRS = ['src', 'poster', 'alt', 'title', 'controls', 'preload', 'loading'];

    /** @var list<string> */
    private const LINK_ATTRS = ['href', 'target', 'rel'];

    /** @var list<string> */
    private const IFRAME_ATTRS = ['src', 'title', 'allow', 'allowfullscreen', 'loading'];

    /** @var list<string> */
    private const TABLE_ATTRS = ['colspan', 'rowspan'];

    public function sanitize(?string $html): string
    {
        if (! is_string($html) || trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="article-root">'.$html.'</div></body></html>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('article-root');
        if (! $root instanceof DOMElement) {
            return strip_tags($html, '<p><br><strong><em>');
        }

        $this->sanitizeChildren($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return trim($result);
    }

    private function sanitizeChildren(DOMElement $parent): void
    {
        for ($i = $parent->childNodes->length - 1; $i >= 0; $i--) {
            $node = $parent->childNodes->item($i);
            if (! $node instanceof DOMNode) {
                continue;
            }

            if ($node->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if ($node->nodeType !== XML_ELEMENT_NODE || ! $node instanceof DOMElement) {
                $parent->removeChild($node);

                continue;
            }

            $tag = strtolower($node->nodeName);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->unwrapElement($node);

                continue;
            }

            if ($tag === 'div' && ! $this->isAllowedEmbedDiv($node)) {
                $this->unwrapElement($node);

                continue;
            }

            if ($tag === 'iframe' && ! $this->isAllowedIframe($node)) {
                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeAttributes($node, $tag);
            $this->sanitizeChildren($node);
        }
    }

    private function unwrapElement(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (! $parent instanceof DOMElement && ! $parent instanceof DOMDocument) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function isAllowedEmbedDiv(DOMElement $element): bool
    {
        $class = $element->getAttribute('class');

        return str_contains($class, 'article-embed');
    }

    private function isAllowedIframe(DOMElement $element): bool
    {
        $src = trim($element->getAttribute('src'));
        if ($src === '') {
            return false;
        }

        return $this->isAllowedIframeSrc($src);
    }

    private function isAllowedIframeSrc(string $src): bool
    {
        $parts = parse_url($src);
        if (! is_array($parts)) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = $parts['path'] ?? '';

        if (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            return str_starts_with($path, '/embed/');
        }

        if ($host === 'facebook.com') {
            return $path === '/plugins/video.php';
        }

        return false;
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = array_merge(
            self::GLOBAL_ATTRS,
            match ($tag) {
                'a' => self::LINK_ATTRS,
                'img', 'video', 'audio' => self::MEDIA_ATTRS,
                'iframe' => self::IFRAME_ATTRS,
                'th', 'td' => self::TABLE_ATTRS,
                default => [],
            },
        );

        if ($element->hasAttributes()) {
            $toRemove = [];
            foreach ($element->attributes as $attribute) {
                $name = strtolower($attribute->nodeName);
                if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                    $toRemove[] = $name;
                }
            }

            foreach ($toRemove as $name) {
                $element->removeAttribute($name);
            }
        }

        foreach (self::EDITOR_ONLY_ATTRS as $name) {
            $element->removeAttribute($name);
        }

        if (in_array($tag, ['img', 'video', 'audio'], true)) {
            $src = trim($element->getAttribute('src'));
            if ($src !== '' && ! $this->isAllowedMediaSrc($src)) {
                $element->removeAttribute('src');
            }

            if ($tag === 'video') {
                $poster = trim($element->getAttribute('poster'));
                if ($poster !== '' && ! $this->isAllowedMediaSrc($poster)) {
                    $element->removeAttribute('poster');
                }
            }
        }

        if ($tag === 'a') {
            $href = trim($element->getAttribute('href'));
            if ($href !== '' && ! $this->isAllowedLinkHref($href)) {
                $element->removeAttribute('href');
            }
        }

        if ($tag === 'iframe') {
            $src = trim($element->getAttribute('src'));
            if ($src === '' || ! $this->isAllowedIframeSrc($src)) {
                $element->setAttribute('src', 'about:blank');
            }
        }
    }

    private function isAllowedMediaSrc(string $src): bool
    {
        if (str_starts_with($src, '/')) {
            return ! str_starts_with($src, '//');
        }

        $parts = parse_url($src);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        return in_array($scheme, ['http', 'https'], true);
    }

    private function isAllowedLinkHref(string $href): bool
    {
        if (str_starts_with($href, '/')) {
            return ! str_starts_with($href, '//');
        }

        if (str_starts_with($href, '#')) {
            return true;
        }

        if (str_starts_with($href, 'mailto:')) {
            return true;
        }

        $parts = parse_url($href);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        return in_array($scheme, ['http', 'https'], true);
    }
}
