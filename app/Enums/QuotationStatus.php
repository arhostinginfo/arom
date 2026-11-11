<?php

namespace App\Enums;

/*
| Quotation status (CRM changes, Sat 13/10/2026): Draft → Internal Approval → Approved → Sent →
| Customer Viewed/Feedback → Negotiation → Accepted / Rejected / Expired / Hold (the last three with a reason).
*/
enum QuotationStatus: string
{
    case Draft = 'Draft';
    case InternalApproval = 'Internal Approval';
    case Approved = 'Approved';
    case Sent = 'Sent';
    case Feedback = 'Customer Viewed/Feedback';
    case Negotiation = 'Negotiation';
    case Accepted = 'Accepted';
    case Rejected = 'Rejected';
    case Expired = 'Expired';
    case Hold = 'Hold';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Statuses the user sets after the quotation is sent to the customer. */
    public static function customerStatuses(): array
    {
        return [self::Sent, self::Feedback, self::Negotiation, self::Accepted, self::Rejected, self::Expired, self::Hold];
    }

    public function needsReason(): bool
    {
        return in_array($this, [self::Rejected, self::Expired, self::Hold], true);
    }

    /** Only a draft can be changed; after that, Revise makes a new draft revision. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** Sent to the customer: from here the customer-side statuses apply. */
    public function isWithCustomer(): bool
    {
        return in_array($this, self::customerStatuses(), true);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Approved, self::Accepted => 'b-ok',
            self::Rejected, self::Expired => 'b-err',
            self::InternalApproval, self::Hold => 'b-warn',
            self::Sent, self::Feedback, self::Negotiation => 'b-info',
            default => 'b-neu',
        };
    }
}
