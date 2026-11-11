<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A follow-up on an enquiry: Call, Email or WhatsApp, with the next action and its target date.
class Activity extends Model
{
    public const TYPES = ['Call', 'Email', 'WhatsApp'];

    protected $fillable = ['activity_type', 'activity_date', 'details', 'next_action', 'target_date'];

    protected function casts(): array
    {
        return ['activity_date' => 'date', 'target_date' => 'date'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
