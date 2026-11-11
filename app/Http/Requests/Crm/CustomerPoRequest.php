<?php

namespace App\Http\Requests\Crm;

use App\Models\CustomerPo;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\Uom;
use App\Services\CustomerPoService;
use App\Services\PageSetupService;
use App\Support\WorkContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CRM › 6 PO / Agreement received, as before 29/10/2026: the Quotation is mandatory (made from an enquiry of the user's
 * units; a new or changed one must be the current revision, sent to the customer and not Rejected / Expired,
 * CustomerPoService::whyNoPo()), and the client and enquiry come from it (there is no Customer or Enquiry field).
 * Always mandatory too: PO / Agreement no, PO date, Received date, and at least one item or a typed PO value (with items
 * the value is their sum, worked out on save).
 * Items (lines) come from the Item master: active Sales / Both items of the quotation's customer or of no party (S-4, as
 * on the Sales page), any sales item for a prospect's quotation. Items show and are mandatory as set in Admin > Page
 * setup ("Items (Part master)"), as do the other fields. A new PO may also bring up to MAX_DOCS documents.
 * Separate PO menu removed (02/11/2026): moved back from App\Http\Requests\Po; the PO menu's rules (Customer, optional
 * link to CRM, direct PO) are gone.
 */
class CustomerPoRequest extends FormRequest
{
    public const LINE_FIELDS = ['id', 'item_id', 'description', 'quantity', 'uom_id', 'rate'];

    /** Most items on one PO (as a purchase order). */
    public const MAX_LINES = 200;

    /** Largest line amount and PO value: the columns are decimal(15,2) (tester review, 29/10/2026). */
    public const MAX_AMOUNT = 9999999999999.99;

    /** Most documents that can be chosen with a new PO (Fri 23/10/2026); more can be uploaded on the saved PO. */
    public const MAX_DOCS = 5;

    public function authorize(): bool
    {
        // Tester review (01/11/2026): another unit's PO is refused before its fields are checked, so no validation message
        // tells anything about it.
        if ($this->current() && ! in_array($this->current()->unit_id, WorkContext::unitIds(), true)) {
            return false;
        }

        return $this->user()->can($this->route('customer_po') ? 'customer-po.edit' : 'customer-po.add');
    }

    /** The PO being changed, or null for a new PO. */
    public function current(): ?CustomerPo
    {
        $po = $this->route('customer_po');

        return $po instanceof CustomerPo ? $po : null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['po_value', 'po_quantity'] as $f) {
            // A tampered field (an array) is kept as it is, so the numeric rule refuses it instead of an error page
            // (tester review, 01/11/2026).
            $v = $this->input($f);
            $this->merge([$f => is_array($v) ? $v : (str_replace([',', ' '], '', (string) $v) ?: null)]);
        }
        $merge = [];
        if ($this->has('quotation_id')) {
            $v = $this->input('quotation_id');
            // Anything but a plain number (a tampered field) is refused by the integer rule below.
            $merge['quotation_id'] = is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : (is_scalar($v) || $v === null ? null : '?');
        }
        // Items come as one JSON field from the page (well below PHP's max_input_vars), or as lines[n][field].
        if ($this->has('lines_json') || $this->has('lines')) {
            $lines = $this->has('lines_json') ? json_decode((string) $this->input('lines_json'), true) : $this->input('lines', []);
            $merge['lines'] = self::cleanLines(is_array($lines) ? $lines : []);
        }
        $this->merge($merge);
    }

    /** Drop rows where nothing was typed; numbers may be typed with commas. */
    public static function cleanLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            // A value that is not plain text or a number (a tampered row) is read as blank (tester review, 29/10/2026), so the
            // form can show the row again after the error.
            $line = array_map(fn ($v) => is_string($v) ? trim($v) : (is_scalar($v) ? $v : null), array_intersect_key($line, array_flip(self::LINE_FIELDS)));
            foreach (['quantity', 'rate'] as $n) {
                if (isset($line[$n]) && is_string($line[$n])) {
                    $line[$n] = str_replace([',', ' '], '', $line[$n]);
                }
            }
            // A row with only its UOM filled in (from the item) and nothing typed is empty.
            if (array_filter(array_intersect_key($line, array_flip(['item_id', 'description', 'quantity', 'rate'])), fn ($v) => $v !== null && $v !== '')) {
                $out[] = $line;
            }
        }

        return $out;
    }

    public function rules(): array
    {
        return array_merge($this->rulesForFields(), $this->lineRules(), $this->current() ? [] : $this->documentRules());
    }

    /** What the service gets: the checked fields; the service takes the client, enquiry and unit from the quotation. */
    public function poData(): array
    {
        $data = $this->validated();
        $data['quotation_id'] = (int) $data['quotation_id'];

        return $data;
    }

    /**
     * Documents chosen in the form of a new PO (Fri 23/10/2026), saved with it in the same request: optional, the
     * same document types, file types, size and content check as the Upload box of a saved PO (DocumentRequest).
     */
    private function documentRules(): array
    {
        return [
            'doc_category' => ['nullable', 'required_with:doc_files', Rule::in(\App\Services\DocumentService::categories('customer_po'))],
            'doc_files' => ['nullable', 'array', 'max:'.self::MAX_DOCS],
            'doc_files.*' => ['file', 'max:'.DocumentRequest::maxKb('customer_po'), 'extensions:'.DocumentRequest::MIMES, DocumentRequest::contentRule()],
        ];
    }

    /** True when this request reads the items: while "Items (Part master)" shows in Admin > Page setup. */
    public function readsLines(): bool
    {
        return app(PageSetupService::class)->visible('customer-po', 'crm_lines');
    }

    /** True when items are sent and at least one is left after dropping blank rows. */
    private function hasLines(): bool
    {
        return $this->readsLines() && is_array($this->input('lines')) && count($this->input('lines')) > 0;
    }

    /**
     * Items (29/10/2026): optional, but a PO needs items or a typed PO value; mandatory when set so in Admin > Page setup.
     * While hidden, the items are not read, so what is saved stays as it was.
     */
    private function lineRules(): array
    {
        if (! $this->readsLines()) {
            return [];
        }
        $need = app(PageSetupService::class)->required('customer-po', 'crm_lines');

        return [
            'lines' => $need ? ['required', 'array', 'min:1', 'max:'.self::MAX_LINES] : ['nullable', 'array', 'max:'.self::MAX_LINES],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            // Saved to 3 decimals: less than 0.001 would be saved as 0 (tester review, 29/10/2026).
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999999'],
            'lines.*.uom_id' => ['nullable', 'integer'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ];
    }

    private function rulesForFields(): array
    {
        // The quotation is mandatory, made from an enquiry of the user's units; whether it can take a PO is checked in
        // after().
        return ['quotation_id' => ['required', 'integer', Rule::exists('quotations', 'id')->whereNull('deleted_at')->whereNotNull('enquiry_id')->whereIn('unit_id', WorkContext::unitIds())]]
            + app(PageSetupService::class)->rules('customer-po', [
                'po_no' => ['required', 'string', 'max:60'],
                'po_date' => ['required', 'date_format:d/m/Y'],
                'site' => ['nullable', 'string', 'max:255'],
                // With items the value is their sum, worked out on save; without items it must be typed.
                // Tester review (01/11/2026): with items the value sent is not read at all ('exclude'), so a non-numeric
                // value cannot reach the database before the sum is worked out.
                'po_value' => $this->hasLines() ? ['exclude'] : ['required', 'numeric', 'min:0', 'max:9999999999999'],
                'po_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
                'valid_from' => ['nullable', 'date_format:d/m/Y'],
                'valid_to' => ['nullable', 'date_format:d/m/Y'],
                'payment_terms' => ['nullable', 'string', 'max:255'],
                'tax_terms' => ['nullable', 'string', 'max:150'],
                'contact_name' => ['nullable', 'string', 'max:150'],
                'received_by' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
                'received_date' => ['required', 'date_format:d/m/Y'],
                'remarks' => ['nullable', 'string', 'max:2000'],
            ]);
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $current = $this->current();
            $this->checkQuotation($v, $current);
            $this->checkLines($v, $current);

            // Validity hidden in Admin > Page setup: the dates are not read, so not compared either.
            if (! app(PageSetupService::class)->visible('customer-po', 'validity')) {
                return;
            }
            $from = $this->input('valid_from');
            $to = $this->input('valid_to');
            if ($from && $to && ! $v->errors()->hasAny(['valid_from', 'valid_to'])
                && \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $to)->lt(\Illuminate\Support\Carbon::createFromFormat('d/m/Y', $from))) {
                $v->errors()->add('valid_to', 'The end date must be on or after the start date.');
            }
        }];
    }

    /**
     * As before 29/10/2026: a new or changed quotation must be able to take a PO (current revision, sent to the customer,
     * not Rejected / Expired); a saved PO keeps its quotation.
     */
    private function checkQuotation(Validator $v, ?CustomerPo $current): void
    {
        if ($v->errors()->has('quotation_id')) {
            return;
        }
        $id = (int) $this->input('quotation_id');
        if ($current && $id === (int) $current->quotation_id) {
            return;
        }
        $q = Quotation::whereIn('unit_id', WorkContext::unitIds())->find($id);
        if ($q && ($why = CustomerPoService::whyNoPo($q))) {
            $v->errors()->add('quotation_id', $why);
        }
    }

    /**
     * Items: active Sales / Both items of the quotation's customer or of no party (S-4), any sales item for a prospect's
     * quotation; an item already on this PO (since made inactive, say) is kept while the customer is unchanged.
     * UOM: an active UOM or one already on this PO.
     */
    private function checkLines(Validator $v, ?CustomerPo $current): void
    {
        $lines = $this->input('lines');
        if (! $this->readsLines() || ! is_array($lines) || ! $lines) {
            return;
        }
        $party = (int) Quotation::whereIn('unit_id', WorkContext::unitIds())->find((int) $this->input('quotation_id'))?->party_id;
        if (! $party && $v->errors()->has('quotation_id')) {
            // The quotation error already says what to choose.
            return;
        }
        $onPo = $current ? $current->lines()->get(['item_id', 'uom_id']) : collect();
        $keepItems = $current && $party === (int) $current->party_id ? $onPo->pluck('item_id')->all() : [];
        $ids = array_values(array_filter(array_map('intval', array_column($lines, 'item_id'))));
        $ok = Item::whereIn('id', $ids)->where(fn ($q) => $q->where(fn ($s) => $s->forSale()
            ->when($party, fn ($p) => $p->where(fn ($w) => $w->whereNull('party_id')->orWhere('party_id', $party))))
            ->orWhereIn('id', $keepItems))->pluck('id')->all();
        $uoms = Uom::forPicker()->orWhereIn('id', $onPo->pluck('uom_id')->filter()->all())->pluck('id')->all();
        $prices = Item::whereIn('id', $ids)->pluck('price', 'id');
        $sum = 0.0;
        foreach (array_values($lines) as $i => $l) {
            $n = $i + 1;
            if (! empty($l['item_id']) && ! in_array((int) $l['item_id'], $ok, true) && ! $v->errors()->has("lines.$i.item_id")) {
                $v->errors()->add("lines.$i.item_id", "Item $n is not an active sales item of this customer.");
            }
            if (! empty($l['uom_id']) && ! in_array((int) $l['uom_id'], $uoms, true) && ! $v->errors()->has("lines.$i.uom_id")) {
                $v->errors()->add("lines.$i.uom_id", "Choose an active UOM for item $n.");
            }
            // Amount and PO value are decimal(15,2) (tester review, 29/10/2026): MariaDB refuses a larger number with an
            // error page, so it is caught here. A blank rate is the item's price.
            if ($v->errors()->hasAny(["lines.$i.quantity", "lines.$i.rate"]) || ! is_numeric($l['quantity'] ?? null)) {
                continue;
            }
            $rate = is_numeric($l['rate'] ?? null) ? (float) $l['rate'] : (float) ($prices[(int) ($l['item_id'] ?? 0)] ?? 0);
            $amount = round((float) $l['quantity'], 3) * round($rate, 4);
            if ($amount > self::MAX_AMOUNT) {
                $v->errors()->add("lines.$i.quantity", "The amount of item $n is too large. Check the quantity and rate.");
            }
            $sum += $amount;
        }
        if ($sum > self::MAX_AMOUNT && ! $v->errors()->has('lines')) {
            $v->errors()->add('lines', 'The items total is too large for one PO.');
        }
    }

    public function messages(): array
    {
        return [
            'quotation_id.required' => 'Choose the quotation this PO is for.',
            'quotation_id.integer' => 'Choose a quotation from the list.',
            'quotation_id.exists' => 'Choose a quotation made from an enquiry of your units.',
            'po_value.required' => 'Add at least one item, or enter the PO value.',
            'lines.required' => 'Add at least one item with its quantity.',
            'lines.min' => 'Add at least one item with its quantity.',
            'lines.max' => 'A PO can have up to '.self::MAX_LINES.' items.',
            'lines.*.item_id.required' => 'Choose the item.',
            'lines.*.quantity.required' => 'Enter the quantity.',
            'lines.*.quantity.min' => 'The quantity must be at least 0.001.',
            'lines.*.rate.numeric' => 'Enter the rate as a number.',
            'po_date.date_format' => 'Enter the PO date as dd/mm/yyyy.',
            'valid_from.date_format' => 'Enter the start date as dd/mm/yyyy.',
            'valid_to.date_format' => 'Enter the end date as dd/mm/yyyy.',
            'received_date.date_format' => 'Enter the received date as dd/mm/yyyy.',
            'po_value.numeric' => 'Enter the PO value as a number.',
            'doc_category.required_with' => 'Choose what kind of document this is.',
            'doc_category.in' => 'Choose what kind of document this is.',
            'doc_files.max' => 'Choose up to '.self::MAX_DOCS.' documents here; upload more on the saved PO.',
            'doc_files.*.max' => 'Each document must not be larger than '.\App\Support\UploadLimit::label(DocumentRequest::maxKb('customer_po')).'.',
            'doc_files.*.extensions' => 'Upload a PDF, image (JPG, PNG, WEBP), Word, Excel, CSV, text or email file.',
        ];
    }

    public function attributes(): array
    {
        return ['quotation_id' => 'quotation', 'po_no' => 'PO / agreement no', 'po_date' => 'PO date', 'po_value' => 'PO value', 'po_quantity' => 'PO quantity',
            'valid_from' => 'start date', 'valid_to' => 'end date', 'tax_terms' => 'GST / tax', 'contact_name' => 'customer contact', 'received_by' => 'received by', 'received_date' => 'received date',
            'doc_category' => 'document type', 'doc_files' => 'documents', 'doc_files.*' => 'document', 'lines' => 'items'];
    }
}
