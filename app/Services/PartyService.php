<?php

namespace App\Services;

use App\Http\Requests\Admin\PartyRequest;
use App\Models\Currency;
use App\Models\Party;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Party master (ADMIN tab A19:J20, built with the Sales module 15/10/2026): customers and suppliers. Each change
 * writes its activity log row in the same transaction. A party in use cannot be deleted; make it Inactive.
 */
class PartyService
{
    /** CSV columns for import and download, in the order of the workbook. */
    public const COLUMNS = [
        'name' => 'Name',
        'gstin' => 'GST no',
        'type' => 'Type',
        'billing_address' => 'Billing address',
        'shipping_address' => 'Shipping address',
        'payment_terms' => 'Payment terms',
        'opening_balance' => 'Opening balance',
        'opening_balance_type' => 'DR / CR',
        'currency' => 'Currency',
        'status' => 'Status',
    ];

    /** Records that point at a party (table.column). */
    public const USED = ['enquiries.party_id', 'quotations.party_id', 'customer_pos.party_id', 'contacts.party_id', 'items.party_id',
        'sales_orders.party_id', 'sales_invoices.party_id', 'purchase_orders.party_id', 'grns.party_id', 'grns.customer_id'];

    public function save(?Party $party, array $data, User $actor): Party
    {
        return DB::transaction(function () use ($party, $data, $actor) {
            $isNew = ! $party;
            $party = $isNew ? new Party() : Party::whereKey($party->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($party);
            $party->fill(array_intersect_key($data, array_flip(Party::FIELDS)));
            self::defaults($party);
            if ($isNew) {
                $party->created_by = $actor->id;
            }
            $party->updated_by = $actor->id;
            $party->save();
            if ($isNew) {
                ActivityLogger::log('party.created', $party, 'Added '.$party->activityLabel(), ['fields' => $this->snapshot($party)]);
            } elseif ($changes = ActivityLogger::diff($before, $this->snapshot($party))) {
                ActivityLogger::log('party.updated', $party, 'Updated '.$party->activityLabel(), $changes);
            }

            return $party;
        });
    }

    public function delete(Party $party, User $actor): void
    {
        DB::transaction(function () use ($party) {
            $party = Party::whereKey($party->id)->lockForUpdate()->firstOrFail();
            $count = 0;
            foreach (self::USED as $ref) {
                [$table, $column] = explode('.', $ref);
                $count += DB::table($table)->where($column, $party->id)->count();
            }
            if ($count) {
                throw ValidationException::withMessages(['delete' => $party->name.' is used on '.$count.' record(s), so it cannot be deleted. Make it Inactive instead.']);
            }
            ActivityLogger::log('party.deleted', $party, 'Deleted '.$party->activityLabel(), ['fields' => $this->snapshot($party)]);
            $party->delete();
        });
    }

    /**
     * Import adds every row of the file as a new party. The whole file is checked first: if any row has an error
     * nothing is saved and the errors list the row numbers (decision C4).
     */
    public function import(array $rows, User $actor): int
    {
        $currencies = Currency::pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id]);
        $valid = [];
        $errors = [];
        $names = [];
        foreach ($rows as $row) {
            $data = [];
            foreach (self::COLUMNS as $field => $label) {
                $data[$field] = ($row[mb_strtolower($label)] ?? '') !== '' ? $row[mb_strtolower($label)] : null;
            }
            $code = strtoupper((string) ($data['currency'] ?: 'INR'));
            $data['currency_id'] = $currencies[$code] ?? null;
            unset($data['currency']);
            $data['status'] ??= 'Active';
            $data = array_merge($data, PartyRequest::normalise($data));
            $v = Validator::make($data, PartyRequest::fieldRules(null), PartyRequest::fieldMessages(), PartyRequest::fieldAttributes());
            $local = [];
            if (! $data['currency_id']) {
                $local[] = 'currency "'.$code.'" is not in the Currency master';
            }
            if ($data['gstin'] && isset($names['g:'.$data['gstin']])) {
                $local[] = 'the GST no is also on row '.$names['g:'.$data['gstin']];
            }
            if ($v->fails() || $local) {
                foreach (array_merge($local, $v->errors()->all()) as $msg) {
                    $errors[] = 'Row '.$row['_row'].': '.$msg;
                }

                continue;
            }
            if ($data['gstin']) {
                $names['g:'.$data['gstin']] = $row['_row'];
            }
            $valid[] = $v->validated();
        }
        if ($errors) {
            throw ValidationException::withMessages(['file' => array_slice($errors, 0, 30)]);
        }

        return DB::transaction(function () use ($valid, $actor) {
            foreach ($valid as $data) {
                $p = new Party(array_intersect_key($data, array_flip(Party::FIELDS)));
                self::defaults($p);
                $p->created_by = $actor->id;
                $p->updated_by = $actor->id;
                $p->save();
            }
            ActivityLogger::log('party.imported', null, 'Imported '.count($valid).' part(ies) from a CSV file', ['added' => array_column($valid, 'name')]);

            return count($valid);
        });
    }

    /** Blank opening balance = 0 DR; a new party is Active unless the form says otherwise. */
    private static function defaults(Party $p): void
    {
        $p->opening_balance ??= 0;
        $p->opening_balance_type ??= 'DR';
        $p->status ??= 'Active';
    }

    /** Values shown in the log: what the user sees on the form. */
    public function snapshot(Party $p): array
    {
        return [
            'Name' => $p->name,
            'GST no' => $p->gstin,
            'Type' => $p->type,
            'Billing address' => $p->billing_address,
            'Shipping address' => $p->shipping_address,
            'Payment terms' => $p->payment_terms,
            'Opening balance' => $p->exists || $p->opening_balance ? number_format((float) $p->opening_balance, 2, '.', '').' '.$p->opening_balance_type : null,
            'Currency' => $p->currency_id ? Currency::find($p->currency_id)?->code : null,
            'Status' => $p->status,
        ];
    }
}
