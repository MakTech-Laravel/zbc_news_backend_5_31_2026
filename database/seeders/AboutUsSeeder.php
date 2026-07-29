<?php

namespace Database\Seeders;

use App\Models\AboutUsSetting;
use Illuminate\Database\Seeder;

class AboutUsSeeder extends Seeder
{
    public function run(): void
    {
        AboutUsSetting::query()->firstOrCreate([], self::defaultContent());
    }

    public static function defaultContent(): array
    {
        return [
            'hero_title' => 'About ZBC News',
            'hero_subtitle' => 'Independent journalism. Bold storytelling. Unbiased reporting.',
            'intro_html' => '<p>Since 2018, ZBC News has been dedicated to delivering <strong>fearless, fact-based journalism</strong> that holds power accountable. We believe informed citizens make better decisions, and our mission is to provide the depth, context, and analysis that today\'s fast-paced news cycle often overlooks. From Washington to Nairobi, our global team of correspondents brings you stories that matter.</p>',
            'values' => [
                [
                    'icon' => 'ShieldCheck',
                    'title' => 'Integrity',
                    'description' => 'Every fact is verified by multiple sources. We correct errors promptly and transparently.',
                ],
                [
                    'icon' => 'Bolt',
                    'title' => 'Speed',
                    'description' => 'Breaking news delivered in real-time without sacrificing accuracy or context.',
                ],
                [
                    'icon' => 'Eye',
                    'title' => 'Depth',
                    'description' => 'We go beyond the headlines to provide analysis, background, and diverse perspectives.',
                ],
            ],
            'leadership_subtitle' => 'Led by award-winning journalists and editors with decades of combined experience',
            'leaders' => [
                [
                    'name' => 'Sarah Johnson',
                    'role' => 'Editor-in-Chief',
                    'bio' => '20+ years in investigative journalism, Pulitzer Prize winner 2019',
                    'initials' => 'SJ',
                ],
                [
                    'name' => 'Marcus Chen',
                    'role' => 'Managing Editor',
                    'bio' => 'Former NYT correspondent, specialized in global affairs',
                    'initials' => 'MC',
                ],
                [
                    'name' => 'Elena Rodriguez',
                    'role' => 'Senior Political Editor',
                    'bio' => '15 years covering Washington, Georgetown journalism faculty',
                    'initials' => 'ER',
                ],
                [
                    'name' => 'David Kim',
                    'role' => 'Technology Editor',
                    'bio' => 'MIT graduate, covered Silicon Valley for a decade',
                    'initials' => 'DK',
                ],
                [
                    'name' => 'Amara Okonkwo',
                    'role' => 'International Correspondent',
                    'bio' => 'Reports from 40+ countries, fluent in 5 languages',
                    'initials' => 'AO',
                ],
                [
                    'name' => 'James Foster',
                    'role' => 'Investigations Director',
                    'bio' => 'Led award-winning investigations into corporate fraud',
                    'initials' => 'JF',
                ],
            ],
            'journey' => [
                [
                    'year' => '2018',
                    'short_year' => '18',
                    'description' => 'ZBC News founded with mission to deliver unbiased reporting',
                ],
                [
                    'year' => '2020',
                    'short_year' => '20',
                    'description' => 'Reached 1 million monthly readers',
                ],
                [
                    'year' => '2022',
                    'short_year' => '22',
                    'description' => 'Won Edward R. Murrow Award for investigative journalism',
                ],
                [
                    'year' => '2024',
                    'short_year' => '24',
                    'description' => 'Expanded to 50+ countries with global correspondent network',
                ],
                [
                    'year' => '2026',
                    'short_year' => '26',
                    'description' => 'Launched AI-powered fact-checking initiative',
                ],
            ],
        ];
    }
}
