<?php

namespace App\Enums;

// Enquiry "Prospect type" (CRM changes).
enum ProspectType: string
{
    case New = 'New';
    case ExistingClient = 'Existing Client';
    case Referral = 'Referral';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
