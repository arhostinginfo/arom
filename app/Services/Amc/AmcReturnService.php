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
    // (23/11/2026) The entry's own fields; the planter, plant, quantities and rate are on its rows.
    // (23/11/2026) Quality is on each row now.
    public const FIELDS = ['amc_client_id', 'request_date', 'replace_date', 'reported_by_id', 'amc_type_id', 'gardener_id', 'return_remark'];

    public function save(?AmcReturn $r, array $data, User $actor): AmcReturn
    {
        return DB::transaction(function () use ($r, $data, $actor) {
            $isNew = ! $r;
            $r = $isNew ? new AmcReturn() : AmcReturn::whereKey($r->id)->lockForUpdate()->firstOrFail();
            $before = $isNew ? [] : $this->snapshot($r);
            $r->fill(array_intersect_key($data, array_flip(self::FIELDS)));
            $rows = array_values($data['items'] ?? []);
            $lines = AmcClientLine::where('amc_client_id', $r->amc_client_id)->whereIn('id', array_filter(array_column($rows, 'amc_client_line_id')))->get()->keyBy('id');
            $first = $lines->get((int) ($rows[0]['amc_client_line_id'] ?? 0));
            $r->location_1 = ($data['location_1'] ?? null) !== null && $data['location_1'] !== '' ? $data['location_1'] : $first?->location_1;
            // The one-row columns of entries made before the rows (21/11/2026): the rows hold these now.
            $r->amc_client_line_id = null;
            $r->location_2 = null;
            $r->amc_plant_id = null;
            $r->plant_size = null;
            $r->qty = null;
            $r->rate = null;
            $r->quality = null;
            if ($isNew) {
                $r->created_by = $actor->id;
            }
            $r->updated_by = $actor->id;
            $r->save();

            // A row of an entry takes the entry's planter and plant (only the one whose qty is given); the plant size and the
            // rate come from the Plant master. A row saved before the entries (no entry) keeps its own planter and plant.
            $resolved = array_map(function ($row) use ($lines) {
                $line = isset($row['amc_client_line_id']) ? $lines->get((int) $row['amc_client_line_id']) : null;
                $pq = $row['planter_qty'] ?? null;
                $q = $row['plant_qty'] ?? null;

                return $row + ['line' => $line,
                    'planter' => $pq === null ? null : ($line ? $line->amc_planter_id : ($row['amc_planter_id'] ?? null)),
                    'plant' => $q === null ? null : ($line ? $line->amc_plant_id : ($row['amc_plant_id'] ?? null))];
            }, $rows);
            $plants = AmcPlant::whereIn('id', array_filter(array_column($resolved, 'plant')))->get(['id', 'size', 'rate'])->keyBy('id');
            // (24/11/2026) a row saved before keeps its plant size and rate when it is saved again with the same plant, so
            // a later change in the Plant master does not rewrite old returns; a new row takes the master's
            $kept = $r->items()->get()->keyBy(fn ($i) => self::keptKey($i->amc_client_line_id, $i->amc_plant_id));
            $r->items()->delete();
            foreach ($resolved as $i => $row) {
                $plant = $row['plant'] ? $plants->get((int) $row['plant']) : null;
                $old = $row['plant'] ? $kept->get(self::keptKey($row['line']?->id, $row['plant'])) : null;
                $r->items()->create([
                    'line_no' => $i + 1,
                    'amc_client_line_id' => $row['line']?->id,
                    'location_2' => $row['line'] ? $row['line']->location_2 : ($row['location_2'] ?? null), // (24/11/2026, test fix) a row without an entry keeps its own
                    'amc_planter_id' => $row['planter'],
                    'planter_qty' => $row['planter'] ? ($row['planter_qty'] ?? null) : null,
                    'amc_plant_id' => $row['plant'],
                    'plant_size' => $old ? $old->plant_size : $plant?->size,
                    'plant_qty' => $row['plant'] ? ($row['plant_qty'] ?? null) : null,
                    'rate' => $old ? $old->rate : $plant?->rate,
                    'quality' => $row['quality'] ?? null,
                ]);
            }
            $r->unsetRelations();
            if ($isNew) {
                ActivityLogger::log('amc_return.created', $r, 'Added Return & Replace '.$r->activityLabel(), ['fields' => $this->snapshot($r)], $actor);
            } elseif ($changes = ActivityLogger::diff($before, $this->snapshot($r))) {
                ActivityLogger::log('amc_return.updated', $r, 'Updated Return & Replace '.$r->activityLabel(), $changes, $actor);
            }

            return $r;
        });
    }

    /** (24/11/2026) Which saved row a posted row is: its entry (or none) and its plant. */
    public static function keptKey($lineId, $plantId): string
    {
        return (int) $lineId.'|'.(int) $plantId;
    }

    /**
     * (24/11/2026, e2e fix) The saved rows of a return keyed by keptKey(), for the page: an entry whose row was taken out
     * and added again is saved with the row's old size and rate, so the page shows those, not the Plant master's.
     */
    public static function savedRows(?int $returnId, ?int $clientId = null)
    {
        if (! $returnId) {
            return collect();
        }

        return \App\Models\Amc\AmcReturnItem::query()->where('amc_return_id', $returnId)->whereNotNull('amc_plant_id')
            ->when($clientId, fn ($q) => $q->whereHas('return', fn ($r) => $r->where('amc_client_id', $clientId)))
            ->get()->keyBy(fn ($i) => self::keptKey($i->amc_client_line_id, $i->amc_plant_id));
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
        $r->loadMissing(['client', 'reportedBy', 'type', 'gardener', 'items.planter', 'items.plant']);
        $df = config('nursery.date_format');
        $snap = [
            'Customer' => $r->client?->name,
            'Location' => $r->location_1,
            'Request date' => $r->request_date?->format($df),
            'Replace date' => $r->replace_date?->format($df),
            'Reported by' => $r->reportedBy?->label(),
            'AMC type' => $r->type?->name,
            'Gardener' => $r->gardener?->label(),
            'Return remark' => $r->return_remark,
        ];
        // (23/11/2026) one history line per row
        foreach ($r->items as $i => $item) {
            $snap['Row '.($i + 1)] = $item->summary();
        }

        return $snap;
    }

    /**
     * (23/11/2026) What has been returned and replaced so far, per entry of the client, for the Return & Replace page:
     * line id => ['state' => done|open|none, 'text' => ...]. A return with a Replace date is replaced; one without is
     * still open. Key '' holds the returns made for a Location without picking an entry, per Location. The return being
     * edited is left out (except from 'count', the History link's count of every return of the entry).
     */
    public static function replaceStatus(int $clientId, ?int $exceptId = null): array
    {
        $out = ['' => []];
        // (23/11/2026) per row of the returns: a return with several rows counts once for each of its entries
        $rows = DB::table('amc_return_items as i')->join('amc_returns as r', 'r.id', '=', 'i.amc_return_id')
            ->where('r.amc_client_id', $clientId)
            ->get(['r.id', 'i.amc_client_line_id', 'r.location_1', 'r.request_date', 'r.replace_date', 'i.plant_qty', 'i.planter_qty'])
            ->map(fn ($x) => (object) [
                'id' => $x->id, 'amc_client_line_id' => $x->amc_client_line_id, 'location_1' => $x->location_1,
                'request_date' => \Illuminate\Support\Carbon::parse($x->request_date),
                'replace_date' => $x->replace_date ? \Illuminate\Support\Carbon::parse($x->replace_date) : null,
                'qty' => (int) ($x->plant_qty ?? $x->planter_qty ?? 0),
            ]);
        // (24/11/2026, test fix) the status leaves out the return being edited, but the History (n) count is of every
        // return, as the History page lists them all (also when the return being edited is the entry's only one)
        $all = $rows;
        $rows = $exceptId ? $rows->where('id', '!=', $exceptId) : $rows;
        $kept = $rows->whereNotNull('amc_client_line_id')->groupBy('amc_client_line_id');
        foreach ($all->whereNotNull('amc_client_line_id')->groupBy('amc_client_line_id') as $lineId => $rsAll) {
            $out[$lineId] = ['count' => $rsAll->count()] + self::statusOf($kept->get($lineId, []));
        }
        foreach ($rows->whereNull('amc_client_line_id')->groupBy(fn ($x) => (string) $x->location_1) as $loc => $rs) {
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
            'count' => $rs->count(), // (24/11/2026) how many times returned: the History link
        ];
    }
}
