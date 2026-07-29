<?php

namespace Database\Seeders;

use App\Enums\CareerEmploymentType;
use App\Enums\CareerJobDepartment;
use App\Enums\CareerJobStatus;
use App\Models\CareerJob;
use App\Models\CareersPageSettings;
use Illuminate\Database\Seeder;

class CareersSeeder extends Seeder
{
    public function run(): void
    {
        CareersPageSettings::query()->firstOrCreate([], $this->defaultPageContent());

        foreach ($this->defaultJobs() as $job) {
            CareerJob::query()->updateOrCreate(
                ['slug' => $job['slug']],
                $job,
            );
        }
    }

    public static function defaultPageContent(): array
    {
        return [
            'hero' => [
                'badge' => 'Now Hiring',
                'headline' => 'Tell Stories That Matter. Build Tools for Journalists Who Do.',
                'subheadline' => 'Join 120+ journalists, engineers, and storytellers working to hold power accountable. We\'re hiring across editorial, engineering, audience, and commercial teams.',
                'primary_cta' => 'View Open Positions',
                'secondary_cta' => 'Our Culture',
            ],
            'stats' => [
                ['value' => '120+', 'label' => 'Team Members'],
                ['value' => '22', 'label' => 'Countries'],
                ['value' => '68%', 'label' => 'Remote Roles'],
                ['value' => '4.7★', 'label' => 'Glassdoor'],
            ],
            'perks_section' => [
                'eyebrow' => 'Perks & Benefits',
                'heading' => 'What We Offer',
            ],
            'perks' => [
                [
                    'icon' => '',
                    'title' => 'Full Health Coverage',
                    'description' => 'Medical, dental, and vision for you and your family.',
                ],
                [
                    'icon' => '',
                    'title' => 'Remote-Friendly',
                    'description' => 'Flexible remote and hybrid arrangements across most roles.',
                ],
                [
                    'icon' => '',
                    'title' => 'Learning Budget',
                    'description' => '$2,500/year for journalism conferences, courses, and books.',
                ],
                [
                    'icon' => '',
                    'title' => 'Press Travel',
                    'description' => 'Budget for field reporting, international coverage, and press events.',
                ],
                [
                    'icon' => '',
                    'title' => 'Generous PTO',
                    'description' => '25 days vacation + public holidays. Mandatory minimums enforced.',
                ],
                [
                    'icon' => '',
                    'title' => 'Equipment Stipend',
                    'description' => '$1,200 home office setup + $600 annual refresh.',
                ],
            ],
            'positions_section' => [
                'eyebrow' => 'Roles',
                'heading' => 'Open Positions',
                'search_placeholder' => 'Search roles, teams, skills…',
            ],
            'hiring_section' => [
                'eyebrow' => 'What to Expect',
                'heading' => 'Our Hiring Process',
            ],
            'hiring_steps' => [
                [
                    'number' => '01',
                    'title' => 'Apply Online',
                    'description' => 'Submit your CV, portfolio or work samples, and a brief cover letter.',
                ],
                [
                    'number' => '02',
                    'title' => 'Initial Screen',
                    'description' => '30-minute video call with our People team to discuss your background and the role.',
                ],
                [
                    'number' => '03',
                    'title' => 'Skills Task',
                    'description' => 'A focused take-home exercise relevant to your role. Max 3 hours — we respect your time.',
                ],
                [
                    'number' => '04',
                    'title' => 'Panel Interview',
                    'description' => 'Meet your potential team. Two rounds, covering craft, values, and collaboration style.',
                ],
                [
                    'number' => '05',
                    'title' => 'Offer',
                    'description' => 'We aim to make decisions within 48 hours of your final interview. No ghosting.',
                ],
            ],
            'testimonials_section' => [
                'eyebrow' => 'Team Stories',
                'heading' => 'From Our Team',
            ],
            'testimonials' => [
                [
                    'quote' => 'ZBC News lets me do the kind of journalism I went into this career for — without someone killing stories for business reasons.',
                    'initials' => 'PN',
                    'name' => 'Priya Nair',
                    'role' => 'Investigative Reporter',
                    'rating' => 5,
                ],
                [
                    'quote' => 'The remote culture is genuine. I\'m based in Lagos, collaborate with New York, and have never felt peripheral to the team.',
                    'initials' => 'ED',
                    'name' => 'Emeka Diallo',
                    'role' => 'Africa Correspondent',
                    'rating' => 5,
                ],
                [
                    'quote' => 'As an engineer, I love that our work directly serves journalists doing important public-interest reporting.',
                    'initials' => 'FB',
                    'name' => 'Fiona Blackwood',
                    'role' => 'Senior Engineer',
                    'rating' => 5,
                ],
            ],
            'faq_section' => [
                'eyebrow' => 'Frequently Asked',
                'heading' => 'Candidate FAQ',
            ],
            'faqs' => [
                [
                    'question' => 'Do you sponsor work visas?',
                    'answer' => 'Yes. For exceptional candidates in editorial and engineering roles, we sponsor work visas in the UK, US, and Singapore where eligible.',
                ],
                [
                    'question' => 'Are editorial positions open to freelancers?',
                    'answer' => 'We occasionally hire contract freelancers for special projects, but most editorial roles are full-time staff positions.',
                ],
                [
                    'question' => 'What\'s the salary range?',
                    'answer' => 'We publish salary bands in each job posting and pay at or above industry benchmarks for the role and location.',
                ],
                [
                    'question' => 'Is there a formal newsroom training programme?',
                    'answer' => 'Yes. All new editorial hires complete a 6-week onboarding programme covering our ethics code, production tools, and mentorship pairing.',
                ],
            ],
            'cta' => [
                'heading' => 'Don\'t See Your Role?',
                'description' => 'Send us your CV. We\'re always interested in exceptional journalists and engineers.',
                'button' => 'Get in Touch',
                'button_url' => '/contact',
            ],
        ];
    }

    private function defaultJobs(): array
    {
        $jobs = [
            [
                'slug' => 'senior-investigative-reporter',
                'title' => 'Senior Investigative Reporter',
                'department' => CareerJobDepartment::EDITORIAL->value,
                'employment_type' => CareerEmploymentType::FULL_TIME->value,
                'location' => 'New York, NY',
                'sort_order' => 1,
            ],
            [
                'slug' => 'video-journalist',
                'title' => 'Video Journalist',
                'department' => CareerJobDepartment::MULTIMEDIA->value,
                'employment_type' => CareerEmploymentType::FULL_TIME->value,
                'location' => 'Remote',
                'sort_order' => 2,
            ],
            [
                'slug' => 'data-journalist',
                'title' => 'Data Journalist',
                'department' => CareerJobDepartment::EDITORIAL->value,
                'employment_type' => CareerEmploymentType::FULL_TIME->value,
                'location' => 'Washington D.C.',
                'sort_order' => 3,
            ],
            [
                'slug' => 'mobile-app-engineer',
                'title' => 'Mobile App Engineer',
                'department' => CareerJobDepartment::ENGINEERING->value,
                'employment_type' => CareerEmploymentType::FULL_TIME->value,
                'location' => 'Remote',
                'sort_order' => 4,
            ],
            [
                'slug' => 'newsletter-editor',
                'title' => 'Newsletter Editor',
                'department' => CareerJobDepartment::AUDIENCE->value,
                'employment_type' => CareerEmploymentType::FULL_TIME->value,
                'location' => 'Remote',
                'sort_order' => 5,
            ],
            [
                'slug' => 'advertising-sales-manager',
                'title' => 'Advertising Sales Manager',
                'department' => CareerJobDepartment::COMMERCIAL->value,
                'employment_type' => CareerEmploymentType::FULL_TIME->value,
                'location' => 'New York, NY',
                'sort_order' => 6,
            ],
            [
                'slug' => 'international-correspondent-apac',
                'title' => 'International Correspondent – APAC',
                'department' => CareerJobDepartment::EDITORIAL->value,
                'employment_type' => CareerEmploymentType::FULL_TIME->value,
                'location' => 'Singapore',
                'sort_order' => 7,
            ],
        ];

        return array_map(static function (array $job): array {
            return [
                ...$job,
                'description' => null,
                'status' => CareerJobStatus::PUBLISHED->value,
                'published_at' => now(),
            ];
        }, $jobs);
    }
}
