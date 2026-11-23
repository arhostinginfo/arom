<?php

namespace App\Services\Amc;

use App\Http\Requests\Amc\AmcClientRequest;
use App\Models\Amc\AmcClient;
use App\Models\Amc\AmcClientLine;
use App\Models\Amc\AmcPlant;
use App\Models\Amc\AmcPlanter;
use App\Models\Amc\AmcVertical;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * AMC Client Inventory (21/11/2026): a customer with its business segment and location lines. Lines keep their ids when a
 * client is changed (sent back with the form), so a Return & Replace entry that points at a line keeps pointing at it.
 * Import: one CSV row per line, rows grouped by customer name; the whole file is checked first and nothing is saved on
 * any error.
 */
class AmcClientService
{
    /** CSV columns, also the import template. */
    public const COLUMNS = [
        'name' => 'Customer name',
        'vertical' => 'Business segment',
        'location_1' => 'Location 1',
        'location_2' => 'Location 2',
        'planter' => 'Planter',
        'planter_qty' => 'Planter qty',
        'plant' => 'Plant',
        'plant_qty' => 'Plant qty',
    ];

    public const LINE_FIELDS = ['location_1', 'location_2', 'amc_planter_id', 'planter_qty', 'amc_plant_id', 'plant_qty'];

    public function save(?AmcClient $client, array $data, User $actor): AmcClient
    {
        return DB::transaction(function () use ($client, $data, $actor) {
            $isNew = ! $client;
            $client = $isNew ? new AmcClient() : AmcClient::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $before = $isNew ? [] : $this->snapshot($client);
            $client->fill(['name' => $data['name'], 'amc_vertical_id' => $data['amc_vertical_id']]);
            if (! empty($data['party_id'])) {
                $client->party_id = $data['party_id'];
            }
            if (array_key_exists('gardener_id', $data)) {
                $client->gardener_id = $data['gardener_id'] ?: null;
            }
            if ($isNew) {
                $client->created_by = $actor->id;
            }
            $client->updated_by = $actor->id;
            $client->save();

            $this->saveLines($client, $data['lines'] ?? []);
            $client->unsetRelation('lines');

            if ($isNew) {
                ActivityLogger::log('amc_client.created', $client, 'Added AMC client '.$client->name, ['fields' => $this->snapshot($client)], $actor);
            } elseif ($changes = ActivityLogger::diff($before, $this->snapshot($client))) {
                ActivityLogger::log('amc_client.updated', $client, 'Updated AMC client '.$client->name, $changes, $actor);
            }

            return $client;
        });
    }

    /** Lines with an id of this client are changed, lines without one are added, the client's other lines are removed. */
    private function saveLines(AmcClient $client, array $lines): void
    {
        $existing = $client->lines()->get()->keyBy('id');
        $keep = [];
        foreach (array_values($lines) as $i => $l) {
            $line = isset($l['id']) && $existing->has((int) $l['id']) ? $existing[(int) $l['id']] : new AmcClientLine();
            $line->fill(array_intersect_key($l, array_flip(self::LINE_FIELDS)) + ['line_no' => $i + 1]);
            $line->line_no = $i + 1;
            $line->amc_client_id = $client->id;
            $line->save();
            $keep[] = $line->id;
        }
        $client->lines()->whereNotIn('id', $keep ?: [0])->delete();
    }

    /**
     * Import: rows of the same customer name (any case) make one new client and its lines. A name already in the master
     * is refused (that client is changed on its page), so importing a downloaded file again never doubles its lines.
     * Masters are matched by name.
     *
     * @return int clients added
     */
    public function import(array $rows, User $actor): int
    {
        $lookup = fn ($model) => $model::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $k) => [mb_strtolower((string) $k) => $id]);
        $verticals = $lookup(AmcVertical::class);
        $planters = $lookup(AmcPlanter::class);
        $plants = $lookup(AmcPlant::class);
        $known = AmcClient::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $k) => [mb_strtolower((string) $k) => $id]);

        $groups = [];
        $errors = [];
        foreach ($rows as $row) {
            $cell = fn (string $f) => ($v = trim((string) ($row[mb_strtolower(self::COLUMNS[$f])] ?? ''))) !== '' ? preg_replace('/\s+/', ' ', $v) : null;
            $local = [];
            // Fields whose name is not in its master: reported once here, not again as "required" by the field rules.
            $missing = [];
            $find = function (string $f, $list, string $what, string $key) use ($cell, &$local, &$missing) {
                $v = $cell($f);
                if ($v === null) {
                    return null;
                }
                $id = $list[mb_strtolower($v)] ?? null;
                if (! $id) {
                    $local[] = $what.' "'.$v.'" is not in the '.$what.' master';
                    $missing[] = $key;
                }

                return $id;
            };
            $name = $cell('name');
            // (22/11/2026) the customer must be an active customer of the Party master; its name is the party's
            $party = AmcClientRequest::partyByName($name);
            if ($name !== null && ! $party) {
                $local[] = 'customer "'.$name.'" is not an active customer in the Party master';
            }
            $name = $party?->name ?? $name;
            $vertical = $find('vertical', $verticals, 'business segment', 'amc_vertical_id');
            $line = [
                'location_1' => $cell('location_1'),
                'location_2' => $cell('location_2'),
                'amc_planter_id' => $find('planter', $planters, 'planter', 'lines.0.amc_planter_id'),
                'planter_qty' => $cell('planter_qty'),
                'amc_plant_id' => $find('plant', $plants, 'plant', 'lines.0.amc_plant_id'),
                'plant_qty' => $cell('plant_qty'),
            ];
            $v = Validator::make(['name' => $name, 'amc_vertical_id' => $vertical, 'lines' => [$line]], AmcClientRequest::fieldRules(), AmcClientRequest::fieldMessages(), AmcClientRequest::fieldAttributes());
            $key = mb_strtolower((string) $name);
            if ($party && AmcClient::where('party_id', $party->id)->exists() && ! isset($known[$key])) {
                $local[] = 'customer "'.$name.'" is already in the AMC Client Inventory; change it on its page';
            }
            if ($name && isset($known[$key])) {
                $local[] = 'customer "'.$name.'" is already in the AMC Client Inventory; change it on its page';
            }
            if ($name && isset($groups[$key]) && $vertical && $groups[$key]['amc_vertical_id'] !== $vertical) {
                $local[] = 'the business segment differs from row '.$groups[$key]['_row'].' of the same customer';
            }
            if ($v->fails() || $local) {
                foreach (array_merge($local, collect($v->errors()->messages())->except($missing)->flatten()->all()) as $msg) {
                    $errors[] = 'Row '.$row['_row'].': '.$msg;
                }

                continue;
            }
            $groups[$key] ??= ['name' => $name, 'party_id' => $party->id, 'amc_vertical_id' => $vertical, '_row' => $row['_row'], 'lines' => []];
            // A row with nothing but the customer name and segment adds the client with no line.
            if (array_filter($line, fn ($x) => $x !== null)) {
                foreach (['planter_qty', 'plant_qty'] as $q) {
                    $line[$q] = $line[$q] === null ? null : (int) $line[$q];
                }
                $groups[$key]['lines'][] = $line;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages(['file' => array_slice($errors, 0, 30)]);
        }

        return DB::transaction(function () use ($groups, $actor) {
            $added = [];
            foreach ($groups as $g) {
                $client = new AmcClient(['party_id' => $g['party_id'], 'name' => $g['name'], 'amc_vertical_id' => $g['amc_vertical_id']]);
                $client->created_by = $actor->id;
                $client->updated_by = $actor->id;
                $client->save();
                foreach ($g['lines'] as $i => $l) {
                    $line = new AmcClientLine($l);
                    $line->line_no = $i + 1;
                    $line->amc_client_id = $client->id;
                    $line->save();
                }
                $added[] = $client->name;
            }
            ActivityLogger::log('amc_client.imported', null, 'Imported '.count($added).' AMC client(s) from a CSV file', ['added' => $added], $actor);

            return count($added);
        });
    }

    /** Values shown in the log: the header and one text per line. */
    public function snapshot(AmcClient $c): array
    {
        $c->loadMissing(['vertical', 'lines.planter', 'lines.plant']);
        $c->load('gardener');
        $out = ['Customer name' => $c->name, 'Business segment' => $c->vertical?->name, 'Gardener' => $c->gardener?->label()];
        foreach ($c->lines as $l) {
            $out['Line '.$l->line_no] = self::lineText($l);
        }

        return $out;
    }

    public static function lineText(AmcClientLine $l): string
    {
        return implode(' · ', array_filter([
            $l->location_1, $l->location_2,
            $l->planter ? $l->planter->name.($l->planter_qty !== null ? ' × '.$l->planter_qty : '') : null,
            $l->plant ? $l->plant->label().($l->plant_qty !== null ? ' × '.$l->plant_qty : '') : null,
        ], fn ($v) => (string) $v !== ''));
    }
}
