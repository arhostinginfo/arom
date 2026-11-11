<?php

namespace App\Http\Requests\Admin;

use App\Services\PageSetupService;
use App\Services\SalesMasterService;
use App\Support\Gst;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit an entry of a master kept through the shared master screen (Tax master, HSN/SAC, UOM, Category,
 * Transporter). Rules come from the field list in config('nursery.sales_masters'); Admin › Page setup decides which
 * optional fields show and which are mandatory.
 */
class SalesMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('id') ? 'masters.edit' : 'masters.add');
    }

    protected function prepareForValidation(): void
    {
        $cfg = SalesMasterService::config((string) $this->route('master'));
        $clean = [];
        foreach ($cfg['fields'] as $f => $field) {
            if (! $this->has($f)) {
                continue;
            }
            $v = preg_replace('/\s+/', ' ', trim((string) $this->input($f)));
            $clean[$f] = in_array($field['type'], ['code', 'gstin'], true) ? strtoupper(str_replace(' ', '', $v)) : $v;
            if ($clean[$f] === '') {
                $clean[$f] = null;
            }
        }
        $this->merge($clean);
    }

    public function rules(): array
    {
        $key = (string) $this->route('master');
        $cfg = SalesMasterService::config($key);
        $fixed = config("nursery.page_fields.master-$key.fields", []);
        $table = (new $cfg['model']())->getTable();
        $rules = [];
        foreach ($cfg['fields'] as $f => $field) {
            $r = [! empty($fixed[$f]['fixed']) ? 'required' : 'nullable'];
            $r = array_merge($r, match ($field['type']) {
                'text' => ['string', 'max:'.($field['max'] ?? 255)],
                'code' => ['string', 'max:'.($field['max'] ?? 20), 'regex:/^[A-Z0-9][A-Z0-9.\/-]*$/'],
                'percent' => ['numeric', 'min:0', 'max:100'],
                'enum' => [Rule::in($field['options'])],
                'select' => ['integer', Rule::exists((new $field['model']())->getTable(), 'id')],
                'gstin' => ['regex:'.Gst::GSTIN],
            });
            if (! empty($field['unique'])) {
                $unique = Rule::unique($table, $f)->ignore($this->route('id'));
                if (! empty($field['unique_with'])) {
                    $unique->where($field['unique_with'], $this->input($field['unique_with']));
                }
                $r[] = $unique;
            }
            $rules[$f] = $r;
        }
        $rules['is_active'] = ['nullable', 'boolean'];

        return app(PageSetupService::class)->rules("master-$key", $rules);
    }

    /** Tax master: CGST and SGST are equal halves of IGST, as GST is charged. */
    public function after(): array
    {
        return [function (Validator $v) {
            if ($this->route('master') !== 'tax-structures' || $v->errors()->hasAny(['cgst_rate', 'sgst_rate', 'igst_rate'])) {
                return;
            }
            $c = (float) $this->input('cgst_rate');
            $s = (float) $this->input('sgst_rate');
            $i = (float) $this->input('igst_rate');
            if (abs($c - $s) > 0.001 || abs(($c + $s) - $i) > 0.001) {
                $v->errors()->add('igst_rate', 'CGST and SGST must be equal and add up to IGST, for example 9 + 9 = 18.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            '*.unique' => 'This is already in the list.',
            '*.regex' => 'Use capital letters, numbers, ".", "/" and "-" only.',
            'gstin.regex' => 'Enter a 15 character GSTIN, for example 27AAACK1234F1Z5.',
        ];
    }

    public function attributes(): array
    {
        return collect(SalesMasterService::config((string) $this->route('master'))['fields'])->map(fn ($f) => strtolower($f['label']))->all();
    }
}
