<?php

namespace App\Enums;

/*
| Where a lead stands. Open until a PO / agreement is received; Lost when its quotation is
| Rejected or Expired. Set by the services, never typed on the form.
*/
enum EnquiryStatus: string
{
    case Open = 'Open';
    case PoReceived = 'PO received';
    case Lost = 'Lost';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'b-info',
            self::PoReceived => 'b-ok',
            self::Lost => 'b-err',
        };
    }
}
