<?php

namespace App\Support;

/*
| Built-in roles the code depends on (decisions D2, D22, U10). Admin gets every permission and
| quotations find their sales people by the Sales role, so both keep their names and cannot be
| deleted. Their permissions on Access rights stay editable, except Admin's (always everything).
*/
final class Roles
{
    public const ADMIN = 'Admin';

    public const SALES = 'Sales';

    /** Roles that cannot be renamed or deleted. */
    public const FIXED = [self::ADMIN, self::SALES];

    public static function isFixed(?string $name): bool
    {
        return in_array($name, self::FIXED, true);
    }
}
