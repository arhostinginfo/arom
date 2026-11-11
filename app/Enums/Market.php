<?php

namespace App\Enums;

// Prospect "Domestic / Export" (workbook CRM tab).
enum Market: string
{
    case Domestic = 'Domestic';
    case Export = 'Export';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
