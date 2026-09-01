<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FacebookVideoUrlResolver
{
    /** @var list<string> */
    private const ALLOWED_HOSTS = [
        'facebook.com',
        'fb.com',
        'fb.watch',
        'm.facebook.com',
        'www.facebook.com',
    ];

    public function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $host = preg_replace('/^m\./', '', $host) ?? $host;

        return in_array($host, ['facebook.com', 'fb.com', 'fb.watch'], true);
    }

    public function isShareUrl(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return (bool) preg_match('#^/share/[vr]/[^/]+/?$#', $path);
    }

    /**
     * Follow Facebook redirects and return a canonical watch / reel / video URL.
     */
    public function resolve(string $url): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '' || ! $this->isAllowedUrl($trimmed)) {
            return null;
        }

        if (! $this->isShareUrl($trimmed) && $this->normalizeCanonical($trimmed) !== null) {
            return $this->normalizeCanonical($trimmed);
        }

        $effectiveUrl = null;

        try {
            $response = Http::withOptions([
                'allow_redirects' => [
                    'max' => 10,
                    'strict' => false,
                    'referer' => false,
                    'track_redirects' => true,
                ],
                'on_stats' => function ($stats) use (&$effectiveUrl) {
                    if (method_exists($stats, 'getEffectiveUri')) {
                        $effectiveUrl = (string) $stats->getEffectiveUri();
                    }
                },
            ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; ZBCNews/1.0; +https://zbc.news)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->timeout(15)
                ->get($trimmed);
        } catch (\Throwable) {
            return null;
        }

        if ($effectiveUrl === null || $effectiveUrl === '') {
            $effectiveUrl = $trimmed;
        }

        if (! $this->isAllowedUrl($effectiveUrl)) {
            return null;
        }

        if ($this->isLoginOrBlockedUrl($effectiveUrl)) {
            return null;
        }

        return $this->normalizeCanonical($effectiveUrl);
    }

    private function isLoginOrBlockedUrl(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return Str::contains($path, [
            '/login',
            '/checkpoint',
            '/privacy/consent',
        ]);
    }

    /**
     * Normalize to an embeddable Facebook video href, or null if unsupported.
     */
    public function normalizeCanonical(string $url): ?string
    {
        if (! $this->isAllowedUrl($url)) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $host = preg_replace('/^m\./', '', $host) ?? $host;
        $path = strtolower($parts['path'] ?? '');
        $query = [];
        parse_str($parts['query'] ?? '', $query);

        if ($host === 'fb.watch') {
            $slug = trim(explode('/', trim($path, '/'))[0] ?? '');
            if ($slug !== '') {
                return "https://fb.watch/{$slug}/";
            }

            return null;
        }

        if ($host !== 'facebook.com' && $host !== 'fb.com') {
            return null;
        }

        if (str_contains($path, '/plugins/video.php')) {
            $href = $query['href'] ?? null;

            return is_string($href) && $href !== ''
                ? $this->normalizeCanonical($href)
                : null;
        }

        if (str_contains($path, '/watch')) {
            $videoId = $query['v'] ?? null;
            if (is_string($videoId) && $videoId !== '') {
                return 'https://www.facebook.com/watch/?v='.$videoId;
            }
        }

        if (
            str_contains($path, '/videos/')
            || str_contains($path, '/reel/')
            || str_contains($path, '/reels/')
            || str_ends_with($path, 'video.php')
        ) {
            $base = 'https://www.facebook.com'.rtrim($parts['path'] ?? '', '/');
            $videoId = $query['v'] ?? null;
            if (is_string($videoId) && $videoId !== '') {
                return $base.'?v='.$videoId;
            }

            return $base.'/';
        }

        if (preg_match('#^/share/[vr]/([^/]+)/?$#', $path, $matches)) {
            $kind = explode('/', trim($path, '/'))[1] ?? 'v';
            $slug = $matches[1] ?? '';

            return $slug !== ''
                ? "https://www.facebook.com/share/{$kind}/{$slug}/"
                : null;
        }

        return null;
    }
}
