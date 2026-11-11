<?php

namespace App\Enums;

// Quotation "Followup status" (decision D13).
enum FollowupStatus: string
{
    case New = 'New';
    case Contacted = 'Contacted';
    case QuotationSent = 'Quotation sent';
    case Negotiation = 'Negotiation';
    case Won = 'Won';
    case Lost = 'Lost';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Won => 'b-ok',
            self::Lost => 'b-err',
            self::Negotiation, self::QuotationSent => 'b-info',
            default => 'b-neu',
        };
    }
}
