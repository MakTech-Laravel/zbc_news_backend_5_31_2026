<?php

namespace Database\Seeders;

use App\Models\AccessibilityStatementSetting;
use Illuminate\Database\Seeder;

class AccessibilityStatementSeeder extends Seeder
{
    public function run(): void
    {
        AccessibilityStatementSetting::query()->firstOrCreate([], self::defaultContent());
    }

    public static function defaultContent(): array
    {
        return [
            'hero_eyebrow' => 'Inclusive Design',
            'hero_title' => 'Accessibility Statement',
            'hero_intro' => 'ZBC News is committed to ensuring that our journalism is accessible to everyone — including the 1.3 billion people worldwide who live with some form of disability.',
            'badges' => [
                ['label' => 'WCAG 2.1 AA Compliant', 'variant' => 'success'],
                ['label' => 'Last Audited: April 2026', 'variant' => 'info'],
                ['label' => 'Next Audit: October 2026', 'variant' => 'info'],
            ],
            'commitment_heading' => 'Journalism Is for Everyone',
            'commitment_paragraphs' => [
                'We believe access to high-quality news is a public right. Barriers to that access — whether a paywall or an inaccessible interface — run counter to our mission.',
                'Our engineering and editorial teams work together to ensure ZBC News meets WCAG 2.1 Level AA as a minimum standard. Formal third-party accessibility audits are conducted bi-annually, with priority issues resolved within 5 business days.',
                'We conduct user testing with people who rely on assistive technology, and we actively maintain an accessibility feedback channel that routes directly to our engineering team.',
            ],
            'commitment_stats' => [
                ['value' => 'WCAG 2.1 AA', 'label' => 'Compliance Standard'],
                ['value' => '2×/year', 'label' => 'Formal Audits'],
                ['value' => '5 days', 'label' => 'Priority Issue Fix'],
                ['value' => '100%', 'label' => 'New Features Tested'],
            ],
            'features_heading' => 'Accessibility Features',
            'features' => [
                [
                    'title' => 'Visual Accessibility',
                    'icon' => 'Eye',
                    'items' => [
                        'WCAG 2.1 AA minimum contrast ratios throughout (4.5:1 body, 3:1 UI)',
                        'Supports browser zoom up to 200% without horizontal scrolling',
                        'Respects prefers-reduced-motion for animations',
                        'High-contrast mode support',
                        'No information conveyed by colour alone',
                    ],
                ],
                [
                    'title' => 'Keyboard Navigation',
                    'icon' => 'Keyboard',
                    'items' => [
                        'All interactive elements reachable via Tab key',
                        'Logical, document-order focus sequence',
                        'Visible focus indicator on all elements',
                        'Skip navigation link (Tab on first load)',
                        'No keyboard traps in modals or overlays',
                    ],
                ],
                [
                    'title' => 'Screen Reader Support',
                    'icon' => 'Volume2',
                    'items' => [
                        'Semantic HTML5 structure throughout',
                        'ARIA roles, labels, and landmark regions',
                        'Descriptive alt text on images',
                        'Form labels associated with inputs',
                        'Tested with NVDA, JAWS, VoiceOver, and TalkBack',
                    ],
                ],
                [
                    'title' => 'Responsive & Adaptive',
                    'icon' => 'MonitorSmartphone',
                    'items' => [
                        'Fully responsive from 320px to 8K displays',
                        'Touch targets minimum 44×44px',
                        'Content reflows at 400% zoom',
                        'Portrait and landscape orientation support',
                        'Print stylesheet for all pages',
                    ],
                ],
            ],
            'shortcuts_heading' => 'Keyboard Shortcuts',
            'keyboard_shortcuts' => [
                ['key' => 'Tab', 'action' => 'Move focus to next interactive element'],
                ['key' => 'Shift + Tab', 'action' => 'Move focus to previous element'],
                ['key' => 'Enter / Space', 'action' => 'Activate button or link'],
                ['key' => 'Escape', 'action' => 'Close modal, dropdown, or overlay'],
                ['key' => 'Arrow Keys', 'action' => 'Navigate within menus, tabboards, or lists'],
                ['key' => 'H', 'action' => 'Jump to next heading (screen reader mode)'],
                ['key' => '1 – 6', 'action' => 'Jump to heading level (screen reader mode)'],
            ],
            'technologies_heading' => 'Supported Technologies',
            'supported_technologies' => [
                ['name' => 'NVDA', 'platform' => 'Windows', 'status' => 'Supported'],
                ['name' => 'JAWS', 'platform' => 'Windows', 'status' => 'Supported'],
                ['name' => 'VoiceOver', 'platform' => 'iOS / macOS', 'status' => 'Supported'],
                ['name' => 'TalkBack', 'platform' => 'Android', 'status' => 'Supported'],
                ['name' => 'Narrator', 'platform' => 'Windows', 'status' => 'Partial'],
                ['name' => 'Orca', 'platform' => 'Linux', 'status' => 'Partial'],
            ],
            'known_limitations' => 'Some third-party embedded content (social media posts, interactive maps, partner video players) may not fully meet our accessibility standards. We are working with our suppliers to address these gaps and welcome reports of any barriers you encounter.',
            'report_heading' => 'Report an Accessibility Issue',
            'report_intro' => 'Encountered a barrier on ZBC News? Tell us. We take every accessibility report seriously and commit to investigating within 5 business days.',
            'contact_email' => 'info@zbc.news',
            'contact_phone' => '+1 (212) 555-0198 ext. 9',
            'contact_address' => '1201 6th Ave, New York, NY',
            'cta_text' => 'Need Immediate Accessibility Help?',
            'cta_button_label' => 'Contact Support',
        ];
    }
}
