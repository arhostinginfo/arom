<?php

namespace App\Enums;

// Status on the Users list (decision D19). Inactive users cannot sign in.
enum UserStatus: string
{
    case Active = 'Active';
    case Inactive = 'Inactive';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
