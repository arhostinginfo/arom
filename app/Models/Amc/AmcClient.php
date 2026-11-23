<?php

namespace App\Models\Amc;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// AMC Client Inventory (21/11/2026): a customer, its business segment and its location lines.
class AmcClient extends Model
{
    protected $table = 'amc_clients';

    protected $fillable = ['party_id', 'name', 'amc_vertical_id', 'gardener_id'];

    /** (22/11/2026) The customer in the Party master; name is copied from it. */
    public function party(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Party::class)->withTrashed();
    }

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

    /** (22/11/2026) The client's gardener (AMC Employee master); a new contract for the client starts with them. */
    public function gardener(): BelongsTo
    {
        return $this->belongsTo(AmcEmployee::class, 'gardener_id');
    }

    /** The client's current Active contract: the one ending last (its gardener is shown on the client list when the client has none). */
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
