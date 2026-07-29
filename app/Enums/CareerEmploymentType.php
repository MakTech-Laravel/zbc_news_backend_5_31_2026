<?php

namespace App\Enums;

enum CareerEmploymentType: string
{
    case FULL_TIME = 'Full-time';
    case CONTRACT = 'Contract';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
