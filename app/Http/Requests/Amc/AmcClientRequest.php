<?php

namespace App\Http\Requests\Amc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add / edit an AMC client: customer name (unique), business segment and its location lines. A line left completely
 * empty is dropped. The import uses the same field rules (fieldRules) row by row.
 */
class AmcClientRequest extends FormRequest
{
    /** Lines on one form: 7 fields each stays well inside PHP's max_input_vars (1,000). Import has no such limit. */
    public const MAX_LINES = 120;

    public function authorize(): bool
    {
        return $this->user()->can($this->route('client') ? 'amc-client.edit' : 'amc-client.add');
    }

    protected function prepareForValidation(): void
    {
        $lines = [];
        foreach ((array) $this->input('lines', []) as $l) {
            if (! is_array($l)) {
                continue;
            }
            $l = array_map(fn ($v) => is_string($v) ? (($v = preg_replace('/\s+/', ' ', trim($v))) === '' ? null : $v) : $v, $l);
            $filled = array_filter(array_intersect_key($l, array_flip(['location_1', 'location_2', 'amc_planter_id', 'planter_qty', 'amc_plant_id', 'plant_qty'])), fn ($v) => $v !== null);
            if ($filled) {
                $lines[] = $l;
            }
        }
        $this->merge([
            'name' => ($n = preg_replace('/\s+/', ' ', trim((string) $this->input('name')))) === '' ? null : $n,
            'lines' => $lines,
        ]);
    }

    public function rules(): array
    {
        $rules = self::fieldRules();
        $rules['name'][] = UniqueName::rule('amc_clients', $this->route('client')?->id, 'This customer is already in the AMC Client Master.');
        $rules['lines'][] = 'max:'.self::MAX_LINES;
        $rules['lines.*.id'] = ['nullable', 'integer'];

        return $rules;
    }

    public static function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'amc_vertical_id' => ['required', 'integer', Rule::exists('amc_verticals', 'id')],
            'lines' => ['array'],
            'lines.*.location_1' => ['nullable', 'string', 'max:150'],
            'lines.*.location_2' => ['nullable', 'string', 'max:150'],
            'lines.*.amc_planter_id' => ['nullable', 'integer', Rule::exists('amc_planters', 'id')],
            'lines.*.planter_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'lines.*.amc_plant_id' => ['nullable', 'integer', Rule::exists('amc_plants', 'id')],
            'lines.*.plant_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public static function fieldMessages(): array
    {
        return [
            'name.unique' => 'This customer is already in the AMC Client Master.',
            'lines.max' => 'A client can have at most '.self::MAX_LINES.' lines on this form. Import a CSV file for more.',
            'lines.*.planter_qty.integer' => 'Planter qty must be a whole number.',
            'lines.*.plant_qty.integer' => 'Plant qty must be a whole number.',
        ];
    }

    public static function fieldAttributes(): array
    {
        return [
            'name' => 'customer name', 'amc_vertical_id' => 'business segment',
            'lines.*.location_1' => 'location 1', 'lines.*.location_2' => 'location 2',
            'lines.*.amc_planter_id' => 'planter', 'lines.*.planter_qty' => 'planter qty',
            'lines.*.amc_plant_id' => 'plant', 'lines.*.plant_qty' => 'plant qty',
        ];
    }

    public function messages(): array
    {
        return self::fieldMessages();
    }

    public function attributes(): array
    {
        return self::fieldAttributes();
    }
}
