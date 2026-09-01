<?php

namespace App\Support;

class SiteSocialContactSettings
{
    /** @var array<string, string> */
    public const DEFAULTS = [
        'social_facebook_url' => 'https://facebook.com/zomibroadcasting',
        'social_x_url' => 'https://x.com/zbcglobalnews',
        'social_linkedin_url' => 'https://www.linkedin.com/company/zbcnews',
        'social_tiktok_url' => 'https://www.tiktok.com/@zbcnews',
        'social_instagram_url' => 'https://www.instagram.com/zomibroadcasting',
        'contact_general_email' => 'info@zbc.news',
        'contact_press_email' => 'newsroom@zbc.news',
        'contact_advertising_email' => 'ads@zbc.news',
        'contact_corrections_email' => 'corrections@zbc.news',
        'contact_office_address' => "425 Fifth Avenue, Suite 1200\nNew York, NY 10016\nUnited States",
        'contact_office_maps_url' => 'https://maps.google.com/?q=425+Fifth+Avenue+Suite+1200+New+York+NY+10016',
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, string>
     */
    public static function resolve(?array $stored): array
    {
        $resolved = self::DEFAULTS;

        if (! is_array($stored)) {
            return $resolved;
        }

        foreach (self::keys() as $key) {
            if (! array_key_exists($key, $stored)) {
                continue;
            }

            $value = $stored[$key];
            $resolved[$key] = is_string($value) ? trim($value) : '';
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    public static function packIntoPayload(array $data, ?array $existing = null): array
    {
        $current = self::resolve($existing);
        $changed = false;

        foreach (self::keys() as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            $current[$key] = is_string($value) ? trim($value) : '';
            unset($data[$key]);
            $changed = true;
        }

        if ($changed) {
            $data['social_contact_settings'] = $current;
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public static function fromModel(mixed $settings): array
    {
        if (! is_object($settings)) {
            return self::DEFAULTS;
        }

        $stored = $settings->social_contact_settings ?? null;

        return self::resolve(is_array($stored) ? $stored : null);
    }
}
