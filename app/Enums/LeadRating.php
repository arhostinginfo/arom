<?php

namespace App\Enums;

// Enquiry "Lead rating" (CRM changes).
enum LeadRating: string
{
    case Hot = 'Hot';
    case Warm = 'Warm';
    case Cold = 'Cold';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Hot => 'b-err',
            self::Warm => 'b-warn',
            self::Cold => 'b-info',
        };
    }
}
