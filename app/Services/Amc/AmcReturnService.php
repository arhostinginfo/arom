<?php

namespace App\Services\Amc;

use App\Models\Amc\AmcClientLine;
use App\Models\Amc\AmcPlant;
use App\Models\Amc\AmcReturn;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Return & Replace (21/11/2026): add, change and delete an entry. The location texts are copied from the picked line of
 * the client and the plant size from the Plant master when saved, so the entry keeps what it was made with.
 */
class AmcReturnService
{
    public const FIELDS = ['amc_client_id', 'amc_client_line_id', 'request_date', 'replace_date', 'reported_by_id', 'amc_type_id', 'amc_plant_id', 'qty', 'rate', 'gardener_id', 'return_remark', 'quality'];

    public function save(?AmcReturn $r, array $data, User $actor): AmcReturn
    {
        return DB::transaction(function () use ($r, $data, $actor) {
            $isNew = ! $r;
            $r = $isNew ? new AmcReturn() : AmcReturn::whereKey($r->id)->lockForUpdate()->firstOrFail();
            $before = $isNew ? [] : $this->snapshot($r);
            $r->fill(array_intersect_key($data, array_flip(self::FIELDS)));
            $line = $r->amc_client_line_id ? AmcClientLine::find($r->amc_client_line_id) : null;
            $r->location_1 = $line?->location_1 ?? (($data['location_1'] ?? '') !== '' ? $data['location_1'] : null);
            $r->location_2 = $line?->location_2;
            $r->plant_size = AmcPlant::find($r->amc_plant_id)?->size;
            if ($isNew) {
                $r->created_by = $actor->id;
            }
            $r->updated_by = $actor->id;
            $r->save();
            $r->unsetRelations();
            if ($isNew) {
                ActivityLogger::log('amc_return.created', $r, 'Added Return & Replace '.$r->activityLabel(), ['fields' => $this->snapshot($r)], $actor);
            } elseif ($changes = ActivityLogger::diff($before, $this->snapshot($r))) {
                ActivityLogger::log('amc_return.updated', $r, 'Updated Return & Replace '.$r->activityLabel(), $changes, $actor);
            }

            return $r;
        });
    }

    public function delete(AmcReturn $r, User $actor): void
    {
        DB::transaction(function () use ($r, $actor) {
            $r = AmcReturn::whereKey($r->id)->lockForUpdate()->firstOrFail();
            ActivityLogger::log('amc_return.deleted', $r, 'Deleted Return & Replace '.$r->activityLabel(), ['fields' => $this->snapshot($r)], $actor);
            $r->delete();
        });
    }

    public function snapshot(AmcReturn $r): array
    {
        $r->loadMissing(['client', 'reportedBy', 'type', 'plant', 'gardener']);
        $df = config('nursery.date_format');

        return [
            'Customer' => $r->client?->name,
            'Location 1' => $r->location_1,
            'Location 2' => $r->location_2,
            'Request date' => $r->request_date?->format($df),
            'Replace date' => $r->replace_date?->format($df),
            'Reported by' => $r->reportedBy?->label(),
            'AMC type' => $r->type?->name,
            'Plant' => $r->plant?->name,
            'Plant size' => $r->plant_size,
            'Qty' => $r->qty !== null ? (string) $r->qty : null,
            'Rate' => $r->rate !== null ? Money::indian($r->rate, 2) : null,
            'Gardener' => $r->gardener?->label(),
            'Return remark' => $r->return_remark,
            'Quality' => $r->quality,
        ];
    }
    /**
     * (23/11/2026) What has been returned and replaced so far, per entry of the client, for the Return & Replace page:
     * line id => ['state' => done|open|none, 'text' => ...]. A return with a Replace date is replaced; one without is
     * still open. Key '' holds the returns made for a Location without picking an entry, per Location. The return being
     * edited is left out.
     */
    public static function replaceStatus(int $clientId, ?int $exceptId = null): array
    {
        $out = ['' => []];
        $returns = AmcReturn::where('amc_client_id', $clientId)->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->get(['id', 'amc_client_line_id', 'location_1', 'request_date', 'replace_date', 'qty']);
        foreach ($returns->whereNotNull('amc_client_line_id')->groupBy('amc_client_line_id') as $lineId => $rs) {
            $out[$lineId] = self::statusOf($rs);
        }
        foreach ($returns->whereNull('amc_client_line_id')->groupBy(fn ($r) => (string) $r->location_1) as $loc => $rs) {
            $out[''][$loc] = self::statusOf($rs);
        }

        return $out;
    }

    /** The status of one group of returns; ['state' => 'none', 'text' => 'Not replaced yet'] when there is none. */
    public static function statusOf($rs): array
    {
        $rs = collect($rs);
        $done = $rs->whereNotNull('replace_date');
        $open = $rs->whereNull('replace_date');
        $parts = [];
        if ($done->count()) {
            $parts[] = 'Replaced '.$done->count().'× ('.$done->sum('qty').' qty), last '.$done->max('replace_date')->format('d/m/Y');
        }
        if ($open->count()) {
            $parts[] = $open->count().' open request'.($open->count() > 1 ? 's' : '').' since '.$open->min('request_date')->format('d/m/Y');
        }

        return [
            'state' => $open->count() ? 'open' : ($done->count() ? 'done' : 'none'),
            'text' => $parts ? implode(' · ', $parts) : 'Not replaced yet',
        ];
    }
}
