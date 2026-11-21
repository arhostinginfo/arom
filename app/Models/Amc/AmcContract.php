<?php

namespace App\Models\Amc;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Customer PO / AMC (21/11/2026): a maintenance contract of an AMC client. Its colour on the AMC list comes from the
 * end date: Green while more than config('amc.due_days') days away, Red when due within them, Black once passed
 * (still Active), Grey when Inactive.
 */
class AmcContract extends Model
{
    public const ACTIVE = 'Active';

    public const INACTIVE = 'Inactive';

    /** Colour key => [label, badge class], in the order of the legend. */
    public const COLOURS = [
        'green' => ['Active', 'b-ok'],
        'red' => ['Due within 30 days', 'b-err'],
        'black' => ['Overdue (end date passed)', 'b-black'],
        'grey' => ['Inactive', 'b-neu'],
    ];

    protected $table = 'amc_contracts';

    protected $fillable = ['amc_client_id', 'amc_type_id', 'start_date', 'end_date', 'billing_cycle', 'visit_plan', 'amount', 'operation_manager_id', 'gardener_id', 'status'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(AmcClient::class, 'amc_client_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AmcType::class, 'amc_type_id');
    }

    public function operationManager(): BelongsTo
    {
        return $this->belongsTo(AmcEmployee::class, 'operation_manager_id');
    }

    public function gardener(): BelongsTo
    {
        return $this->belongsTo(AmcEmployee::class, 'gardener_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(AmcFile::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    /** "AMC-0001". */
    public function number(): string
    {
        return sprintf('AMC-%04d', $this->id);
    }

    public function activityLabel(): string
    {
        return $this->number().' · '.($this->client?->name ?? '');
    }

    /** Days from today to the end date: negative once it has passed. */
    public function daysLeft(?Carbon $today = null): int
    {
        return (int) ($today ?? today())->diffInDays($this->end_date, false);
    }

    public function colour(?Carbon $today = null): string
    {
        if ($this->status !== self::ACTIVE) {
            return 'grey';
        }
        $left = $this->daysLeft($today);

        return $left < 0 ? 'black' : ($left <= config('amc.due_days') ? 'red' : 'green');
    }

    /** Active and the end date has passed: the red pop up on the contract page. */
    public function isOverdue(): bool
    {
        return $this->status === self::ACTIVE && $this->daysLeft() < 0;
    }

    /** Active and ending within config('amc.renew_days') days: the renewal banner. */
    public function needsRenewal(): bool
    {
        $left = $this->daysLeft();

        return $this->status === self::ACTIVE && $left >= 0 && $left <= config('amc.renew_days');
    }

    /**
     * Rows of one colour of the AMC list, in SQL on (status, end_date). Bounds are "before the next day", so a date
     * stored with a time (as SQLite does) compares the same as a MySQL DATE.
     */
    public function scopeOfColour(Builder $query, string $colour): Builder
    {
        $today = today()->toDateString();
        $afterDue = today()->addDays(config('amc.due_days') + 1)->toDateString();

        return match ($colour) {
            'grey' => $query->where('status', self::INACTIVE),
            'black' => $query->where('status', self::ACTIVE)->where('end_date', '<', $today),
            'red' => $query->where('status', self::ACTIVE)->where('end_date', '>=', $today)->where('end_date', '<', $afterDue),
            'green' => $query->where('status', self::ACTIVE)->where('end_date', '>=', $afterDue),
            default => $query,
        };
    }

    /** Active and ending within config('amc.renew_days') days (the dashboard's "Renewals due" tile), same bounds as above. */
    public function scopeDueForRenewal(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE)->where('end_date', '>=', today()->toDateString())
            ->where('end_date', '<', today()->addDays(config('amc.renew_days') + 1)->toDateString());
    }
}
