<?php

namespace App\Enums;

enum CareerJobDepartment: string
{
    case EDITORIAL = 'Editorial';
    case ENGINEERING = 'Engineering';
    case MULTIMEDIA = 'Multimedia';
    case AUDIENCE = 'Audience';
    case COMMERCIAL = 'Commercial';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
