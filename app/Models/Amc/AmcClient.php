<?php

namespace App\Models\Amc;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// AMC Client Master (21/11/2026): a customer, its business segment and its location lines.
class AmcClient extends Model
{
    protected $table = 'amc_clients';

    protected $fillable = ['name', 'amc_vertical_id'];

    public function vertical(): BelongsTo
    {
        return $this->belongsTo(AmcVertical::class, 'amc_vertical_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AmcClientLine::class)->orderBy('line_no')->orderBy('id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(AmcContract::class);
    }

    /** The client's current Active contract: the one ending last (it names the gardener on the client page). */
    public function activeContract(): HasOne
    {
        return $this->hasOne(AmcContract::class)->ofMany(['end_date' => 'max', 'id' => 'max'], fn ($q) => $q->where('status', AmcContract::ACTIVE));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function activityLabel(): string
    {
        return $this->name;
    }
}
