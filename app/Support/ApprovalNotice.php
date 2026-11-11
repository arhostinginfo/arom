<?php

namespace App\Support;

use App\Models\ApprovalRule;

/**
 * What happens on "Submit / Send for approval" (Sat 18/10/2026): instead of a fixed "Approval needed above ₹1 lakh"
 * line, the submit button asks first, naming the amount and the limit from Admin › Approval in Indian format, and
 * saying it goes to an approver. Below the limit it is submitted with no question. The service applies the same rule.
 */
final class ApprovalNotice
{
    /** The question to ask before submitting, or null when the document will be approved at once (no pop-up). */
    public static function message(ApprovalRule $rule, float $amount, ?string $currency, string $what): ?string
    {
        $inRupees = ($currency ?? 'INR') === 'INR';
        $total = ($inRupees ? '₹' : $currency.' ').Money::indian($amount, 2);
        $limit = $rule->approve_above_amount !== null ? '₹'.Money::indian($rule->approve_above_amount, 2) : null;

        if (! $rule->needsApproval($amount, $inRupees)) {
            return null; // re-test S1: below the limit it is submitted straight away
        }
        $why = match (true) {
            $limit === null => "every $what needs approval",
            ! $inRupees => "the approval limit is in rupees, so a $what in $currency always needs approval",
            default => "its total $total is above the approval limit of $limit",
        };

        return "This $what will go to an approver: $why. Continue?";
    }
}
