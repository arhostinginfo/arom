<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// Submit / approve / reject history of a document. Rows are never changed.
class Approval extends Model
{
    public $timestamps = false;

    protected $fillable = ['action', 'amount', 'remarks', 'acted_by', 'acted_at'];

    protected function casts(): array
    {
        return ['acted_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by')->withTrashed();
    }
}
