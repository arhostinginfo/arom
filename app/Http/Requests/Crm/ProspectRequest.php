<?php

namespace App\Http\Requests\Crm;

use App\Enums\Market;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add / edit prospect. Mandatory (decision S4): Prospect name, Domestic / Export, Contact no 1.
 * The same rules check each row of a prospect import.
 */
class ProspectRequest extends FormRequest
{
    public const PHONE = '/^\+?[0-9][0-9 ()-]{5,19}$/';

    public const GSTIN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    public function authorize(): bool
    {
        return $this->user()->can($this->route('prospect') ? 'prospect.edit' : 'prospect.add');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalise($this->all()));
    }

    public function rules(): array
    {
        return self::fieldRules();
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
        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => mb_strtolower(trim((string) ($data['email'] ?? ''))) ?: null,
            'gstin' => strtoupper(preg_replace('/\s+/', '', (string) ($data['gstin'] ?? ''))) ?: null,
        ];
    }

    /** The same rules for the page, the Add prospect drawer and each import row, with Admin > Page setup applied. */
    public static function fieldRules(): array
    {
        return app(\App\Services\PageSetupService::class)->rules('prospect', [
            'name' => ['required', 'string', 'max:150'],
            'market' => ['required', Rule::enum(Market::class)],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'phone_1' => ['required', 'regex:'.self::PHONE],
            'phone_2' => ['nullable', 'regex:'.self::PHONE],
            'email' => ['nullable', 'email', 'max:191'],
            'gstin' => ['nullable', 'regex:'.self::GSTIN],
            'industry_type' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    public static function fieldMessages(): array
    {
        return [
            'phone_1.regex' => 'Enter a phone number of 6 to 20 digits, for example +91 98220 00001.',
            'phone_2.regex' => 'Enter a phone number of 6 to 20 digits, for example +91 98220 00001.',
            'gstin.regex' => 'Enter a 15 character GSTIN, for example 27AAACK1234F1Z5.',
            'market.required' => 'Choose Domestic or Export.',
        ];
    }

    public static function fieldAttributes(): array
    {
        return [
            'name' => 'prospect name',
            'market' => 'domestic / export',
            'phone_1' => 'contact no 1',
            'phone_2' => 'contact no 2',
            'industry_type' => 'industry type',
            'contact_person' => 'contact person',
            'gstin' => 'GSTIN',
        ];
    }
}
