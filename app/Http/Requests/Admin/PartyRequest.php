<?php

namespace App\Http\Requests\Admin;

use App\Models\Party;
use App\Services\PageSetupService;
use App\Support\Gst;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add / edit a party (Party master). Always mandatory: Name, Type, Status; Billing address mandatory by default
 * (S4); the rest as set in Admin › Page setup. The same rules check each row of a party import.
 * The GST no is checked for its format; its first two digits are the state that decides CGST + SGST or IGST.
 */
class PartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('party') ? 'masters.edit' : 'masters.add');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalise($this->all()));
    }

    public function rules(): array
    {
        return self::fieldRules($this->route('party')?->id);
    }

    public function messages(): array
    {
        return self::fieldMessages();
    }

    public function attributes(): array
    {
        return self::fieldAttributes();
    }

    public static function normalise(array $data): array
    {
        $out = [];
        foreach (['name', 'gstin', 'billing_address', 'shipping_address', 'payment_terms'] as $f) {
            if (array_key_exists($f, $data)) {
                $v = trim((string) $data[$f]);
                $out[$f] = $f === 'gstin' ? (strtoupper(preg_replace('/\s+/', '', $v)) ?: null) : ($v === '' ? null : $v);
            }
        }
        if (array_key_exists('opening_balance', $data)) {
            $out['opening_balance'] = str_replace([',', ' '], '', (string) $data['opening_balance']) ?: null;
        }
        foreach (['type', 'status', 'opening_balance_type'] as $f) {
            if (isset($data[$f]) && is_string($data[$f]) && $data[$f] !== '') {
                $out[$f] = $f === 'opening_balance_type' ? strtoupper(trim($data[$f])) : ucfirst(strtolower(trim($data[$f])));
            }
        }

        return $out;
    }

    public static function fieldRules(?int $id): array
    {
        return app(PageSetupService::class)->rules('party', [
            'name' => ['required', 'string', 'max:150'],
            'gstin' => ['nullable', 'regex:'.Gst::GSTIN, Rule::unique('parties', 'gstin')->ignore($id)],
            'type' => ['required', Rule::in(Party::TYPES)],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'opening_balance_type' => ['nullable', Rule::in(['DR', 'CR'])],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    public static function fieldMessages(): array
    {
        return [
            'gstin.regex' => 'Enter a 15 character GST no, for example 27AAACK1234F1Z5.',
            'gstin.unique' => 'Another party has this GST no (a deleted party keeps its GST no).',
            'type.in' => 'Choose Customer, Supplier or Both.',
            'opening_balance.numeric' => 'Enter the opening balance as a number.',
        ];
    }

    public static function fieldAttributes(): array
    {
        return ['gstin' => 'GST no', 'billing_address' => 'billing address', 'shipping_address' => 'shipping address', 'payment_terms' => 'payment terms',
            'opening_balance' => 'opening balance', 'opening_balance_type' => 'DR / CR', 'currency_id' => 'currency'];
    }
}
