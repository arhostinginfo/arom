<?php

namespace App\Http\Requests\Crm;

use App\Models\QuotationCost;
use App\Models\TaxStructure;
use App\Models\Uom;
use App\Models\User;
use App\Support\Gst;
use App\Support\Roles;
use App\Support\WorkContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit quotation (CRM changes, Sat 13/10/2026). Header: Enquiry (gives Quotation no's enquiry, the
 * client and site), Contact, Site, Date, Valid until, Sales person (Sales role, D22), Currency, Payment
 * terms, Delivery / service terms, GST / tax, Discount, Notes. Items: Item no, Item description, Qty,
 * Unit, Rate, Discount %, Tax %; the total is worked out on save. Cost details are internal.
 * The same rules check each quotation of an import.
 * UOM / Tax from the masters (23/10/2026): Unit is a UOM master code and Tax a Tax master row (GST / tax of the
 * quotation too); the Tax % is taken from the structure on save, never from what is sent. A line saved before
 * keeps its own Unit text or Tax % ("old:18") while the quotation is changed.
 */
class QuotationRequest extends FormRequest
{
    public const LINE_FIELDS = ['item_no', 'description', 'qty', 'uom', 'rate', 'discount_percent', 'tax_structure_id'];

    /** Typed fields: a row with only a Unit or Tax picked (e.g. the quotation's default tax) counts as empty (23/10/2026). */
    private const TYPED_FIELDS = ['item_no', 'description', 'qty', 'rate', 'discount_percent'];

    public function authorize(): bool
    {
        return $this->user()->can($this->route('quotation') ? 'quotation.edit' : 'quotation.add');
    }

    protected function prepareForValidation(): void
    {
        // The quotation page sends its items as one JSON field ("lines_json"), so a 200-item quotation stays far
        // below PHP's max_input_vars (full test B1). Without JavaScript the items come as lines[n][field].
        if ($this->has('lines_json')) {
            $decoded = json_decode((string) $this->input('lines_json'), true);
            $this->merge(['lines' => is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : []]);
        }
        $cost = (array) $this->input('cost', []);
        foreach ($cost as $k => $v) {
            $cost[$k] = is_string($v) ? str_replace([',', ' '], '', $v) : $v;
        }
        $this->merge([
            'lines' => self::cleanLines((array) $this->input('lines', [])),
            'discount_amount' => str_replace([',', ' '], '', (string) $this->input('discount_amount')) ?: null,
            'cost' => $cost,
        ]);
        // The enquiry of an existing quotation cannot change.
        if ($q = $this->route('quotation')) {
            $this->merge(['enquiry_id' => $q->enquiry_id]);
        }
    }

    public function rules(): array
    {
        $rules = self::fieldRules(! $this->route('quotation') || (bool) $this->route('quotation')->enquiry_id);
        // Cost details are an optional part: while costing is off they are not read, and saved costs stay as they are.
        if (! \App\Support\Modules::on('costing')) {
            $rules = array_filter($rules, fn ($key) => $key !== 'cost' && ! str_starts_with($key, 'cost.'), ARRAY_FILTER_USE_KEY);
        }

        // Admin > Page setup: hidden header fields are not read; the rest are mandatory or optional as set up.
        return app(\App\Services\PageSetupService::class)->rules('quotation', $rules);
    }

    public function messages(): array
    {
        return self::fieldMessages();
    }

    public function attributes(): array
    {
        return self::fieldAttributes();
    }

    public function after(): array
    {
        return [fn (Validator $v) => self::check($v, $this->all() + ['_existing' => (bool) $this->route('quotation'), '_quotation' => $this->route('quotation')])];
    }

    /** Drop item rows where nothing was typed, and trim the rest. Amounts may be typed with commas. */
    public static function cleanLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $line = array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_intersect_key((array) $line, array_flip(self::LINE_FIELDS)));
            foreach (['qty', 'rate', 'discount_percent'] as $n) {
                if (isset($line[$n]) && is_string($line[$n])) {
                    $line[$n] = str_replace([',', ' '], '', $line[$n]);
                }
            }
            if (array_filter(array_intersect_key($line, array_flip(self::TYPED_FIELDS)), fn ($v) => $v !== null && $v !== '')) {
                $out[] = $line;
            }
        }

        return $out;
    }

    /** $needsEnquiry: false only when changing a quotation saved before enquiries existed. */
    public static function fieldRules(bool $needsEnquiry = true): array
    {
        $rules = [
            'enquiry_id' => [$needsEnquiry ? 'required' : 'nullable', 'integer', Rule::exists('enquiries', 'id')->whereNull('deleted_at')],
            'quotation_date' => ['required', 'date_format:d/m/Y'],
            'valid_until' => ['nullable', 'date_format:d/m/Y'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'site' => ['nullable', 'string', 'max:255'],
            'sales_person_id' => ['required', 'integer'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'delivery_terms' => ['nullable', 'string', 'max:255'],
            // GST / tax is a Tax master drop-down (23/10/2026); the old tax_terms text is no longer read or changed.
            'tax_structure_id' => ['nullable', 'integer'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_no' => ['nullable', 'string', 'max:50'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'lines.*.uom' => ['nullable', 'string', 'max:20'],
            'lines.*.rate' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_structure_id' => ['nullable', 'max:20'],
            'cost' => ['nullable', 'array'],
            'cost.margin_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ];
        foreach (array_keys(QuotationCost::COSTS) as $f) {
            $rules['cost.'.$f] = ['nullable', 'numeric', 'min:0', 'max:9999999999999'];
        }

        return $rules;
    }

    public static function fieldMessages(): array
    {
        return [
            'enquiry_id.required' => 'Choose the enquiry this quotation is for.',
            'enquiry_id.exists' => 'This enquiry no longer exists. Choose another enquiry.',
            'lines.required' => 'Add at least one item.',
            'lines.*.description.required' => 'Item :position needs an item description.',
            'lines.*.qty.required' => 'Item :position needs a quantity.',
            'lines.*.qty.numeric' => 'Item :position: quantity must be a number.',
            'lines.*.qty.gt' => 'Item :position: quantity must be more than 0.',
            'lines.*.rate.required' => 'Item :position needs a rate.',
            'lines.*.rate.numeric' => 'Item :position: rate must be a number.',
            'lines.*.discount_percent.numeric' => 'Item :position: discount % must be a number from 0 to 100.',
            'lines.*.discount_percent.max' => 'Item :position: discount % must be a number from 0 to 100.',
            'tax_structure_id.required' => 'Choose the GST / tax.',
            'tax_structure_id.integer' => 'Choose the GST / tax from the list.',
            'quotation_date.date_format' => 'Enter the quotation date as dd/mm/yyyy.',
            'valid_until.date_format' => 'Enter the valid until date as dd/mm/yyyy.',
            'sales_person_id.required' => 'Choose the sales person.',
            'discount_amount.numeric' => 'Enter the discount as an amount, for example 500.',
            'cost.*.numeric' => 'Cost details must be numbers.',
        ];
    }

    public static function fieldAttributes(): array
    {
        return [
            'enquiry_id' => 'enquiry',
            'quotation_date' => 'quotation date',
            'valid_until' => 'valid until',
            'contact_name' => 'contact',
            'sales_person_id' => 'sales person',
            'currency_id' => 'currency',
            'delivery_terms' => 'delivery / service terms',
            'tax_structure_id' => 'GST / tax',
            'discount_amount' => 'discount',
            'cost.margin_percent' => 'margin %',
        ];
    }

    /** Rules that need the database or more than one field. */
    public static function check(Validator $v, array $data): void
    {
        if (! empty($data['sales_person_id']) && ! $v->errors()->has('sales_person_id')
            && ! self::salesPeopleQuery()?->whereKey((int) $data['sales_person_id'])->exists()) {
            $v->errors()->add('sales_person_id', 'The sales person must be an active user with the Sales role.');
        }
        if (! empty($data['enquiry_id']) && ! $v->errors()->has('enquiry_id') && auth()->check()
            && ! \App\Models\Enquiry::whereKey((int) $data['enquiry_id'])->whereIn('unit_id', WorkContext::unitIds())->exists()) {
            $v->errors()->add('enquiry_id', 'Choose an enquiry of your units.');
        }
        // A new quotation is for an open lead; a Lost or PO-received enquiry is refused (code review S11, full test B5).
        if (! empty($data['enquiry_id']) && empty($data['_existing']) && ! $v->errors()->has('enquiry_id')
            && \App\Models\Enquiry::whereKey((int) $data['enquiry_id'])->where('status', '!=', \App\Enums\EnquiryStatus::Open->value)->exists()) {
            $v->errors()->add('enquiry_id', 'This enquiry is not open (it is Lost or has a PO). Choose an open enquiry.');
        }
        $from = $data['quotation_date'] ?? null;
        $to = $data['valid_until'] ?? null;
        if ($from && $to && ! $v->errors()->hasAny(['quotation_date', 'valid_until'])
            && \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $to)->lt(\Illuminate\Support\Carbon::createFromFormat('d/m/Y', $from))) {
            $v->errors()->add('valid_until', 'Valid until must be on or after the quotation date.');
        }
        self::checkMasters($v, $data);
        if (! $v->errors()->hasAny(['lines', 'discount_amount']) && ! empty($data['discount_amount'])) {
            $sub = 0.0;
            $supply = self::supplyFor($data);
            $structures = TaxStructure::all()->keyBy('id');
            foreach ((array) ($data['lines'] ?? []) as $l) {
                if (is_numeric($l['qty'] ?? null) && is_numeric($l['rate'] ?? null)) {
                    $sub += \App\Models\QuotationLine::total((float) $l['qty'], (float) $l['rate'], (float) (($l['discount_percent'] ?? '') ?: 0), self::taxPercent($l['tax_structure_id'] ?? null, $supply, $structures));
                }
            }
            if ((float) $data['discount_amount'] > $sub) {
                $v->errors()->add('discount_amount', 'The discount cannot be more than the items total ('.\App\Support\Money::indian($sub, 2).').');
            }
        }
    }

    /**
     * UOM / Tax from the masters (23/10/2026): each item's Unit is an active UOM code (any case) and its Tax an active
     * tax structure; the GST / tax of the quotation too. On a change, what the quotation's lines already have (an old
     * Unit text, an inactive UOM or structure, an old Tax % as "old:18") is still accepted, so nothing saved is lost.
     */
    private static function checkMasters(Validator $v, array $data): void
    {
        $q = $data['_quotation'] ?? null;
        $old = $q ? $q->lines()->get(['uom', 'tax_structure_id', 'tax_percent']) : collect();
        $codes = Uom::where('is_active', true)->pluck('code')->map(fn ($c) => mb_strtoupper($c))->all();
        $oldUoms = $old->pluck('uom')->filter()->map(fn ($u) => (string) $u)->all();
        $keepIds = array_filter(array_merge($old->pluck('tax_structure_id')->all(), [$q?->tax_structure_id]));
        $taxIds = TaxStructure::forPicker()->pluck('id')->merge($keepIds)->map(fn ($id) => (string) $id)->unique()->all();
        $oldRates = $old->pluck('tax_percent')->map(fn ($p) => (float) $p)->all();

        $head = $data['tax_structure_id'] ?? null;
        if ($head !== null && $head !== '' && ! $v->errors()->has('tax_structure_id') && ! in_array((string) $head, $taxIds, true)) {
            $v->errors()->add('tax_structure_id', 'Choose the GST / tax from the Tax master.');
        }
        foreach (array_values((array) ($data['lines'] ?? [])) as $i => $l) {
            $n = $i + 1;
            $uom = trim((string) ($l['uom'] ?? ''));
            if ($uom !== '' && ! in_array(mb_strtoupper($uom), $codes, true) && ! in_array($uom, $oldUoms, true)) {
                $v->errors()->add("lines.$i.uom", 'Item '.$n.': choose a unit from the UOM master ("'.$uom.'" is not in it).');
            }
            $tax = trim((string) ($l['tax_structure_id'] ?? ''));
            if ($tax === '') {
                continue;
            }
            $ok = str_starts_with($tax, 'old:')
                ? is_numeric($r = substr($tax, 4)) && in_array((float) $r, $oldRates, true)
                : in_array($tax, $taxIds, true);
            if (! $ok) {
                $v->errors()->add("lines.$i.tax_structure_id", 'Item '.$n.': choose a tax from the Tax master.');
            }
        }
    }

    /** Supply type of the quotation (23/10/2026): by its customer, or the enquiry's for a new one. */
    public static function supplyFor(array $data): string
    {
        $party = ($data['_quotation'] ?? null)?->party
            ?? (! empty($data['enquiry_id']) ? \App\Models\Enquiry::with('party')->find((int) $data['enquiry_id'])?->party : null);

        return Gst::forQuotation($party);
    }

    /**
     * Tax % of an item from what its Tax drop-down sent (23/10/2026): a structure id gives the structure's rate for the
     * supply type; "old:18" keeps a Tax % saved before (checked above); nothing gives 0. $structures: id => TaxStructure.
     */
    public static function taxPercent($value, string $supply, $structures): float
    {
        $value = trim((string) $value);
        if (str_starts_with($value, 'old:')) {
            return round((float) substr($value, 4), 2);
        }

        return $value !== '' && isset($structures[(int) $value]) ? round($structures[(int) $value]->rateFor($supply), 2) : 0.0;
    }

    /** Active users with the Sales role (decision D22). */
    public static function salesPeople()
    {
        return self::salesPeopleQuery()?->orderBy('name')->get(['id', 'name', 'email']) ?? collect();
    }

    /** Query for active users with the Sales role, or null when that role does not exist. */
    public static function salesPeopleQuery()
    {
        if (! \Spatie\Permission\Models\Role::where('name', Roles::SALES)->exists()) {
            return null;
        }

        return User::role(Roles::SALES)->where('status', 'Active');
    }
}
