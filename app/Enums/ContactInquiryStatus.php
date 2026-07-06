<?php

namespace App\Enums;

enum ContactInquiryStatus: string
{
    case NEW = 'new';
    case READ = 'read';
    case REPLIED = 'replied';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::READ => 'Read',
            self::REPLIED => 'Replied',
            self::ARCHIVED => 'Archived',
        };
    }

    public static function filterable(): array
    {
        return array_column(self::cases(), 'value');
    }
}
