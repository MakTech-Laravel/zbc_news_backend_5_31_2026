<?php

namespace App\Enums;

enum CareerApplicationStatus: string
{
    case NEW = 'new';
    case REVIEWED = 'reviewed';
    case SHORTLISTED = 'shortlisted';
    case REJECTED = 'rejected';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::REVIEWED => 'Reviewed',
            self::SHORTLISTED => 'Shortlisted',
            self::REJECTED => 'Rejected',
            self::ARCHIVED => 'Archived',
        };
    }

    public static function filterable(): array
    {
        return array_column(self::cases(), 'value');
    }
}
