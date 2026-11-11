<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Upload a document to an enquiry, site visit, quotation or PO. Up to 10 MB (enquiries: the Admin > Config
 * upload size, default 1 MB, 22/10/2026): PDF, images, Word,
 * Excel, CSV, text and saved emails. Who may upload is checked by the controller against the record.
 */
class DocumentRequest extends FormRequest
{
    public const MIMES = 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt,eml,msg';

    public const MAX_KB = 10240;

    /** Record types whose documents use the upload size of Admin > Config instead of MAX_KB (22/10/2026). */
    public const USES_UPLOAD_LIMIT = ['enquiry'];

    /** Content types each extension may have. */
    private const OLE = ['application/cdfv2', 'application/x-ole-storage', 'application/vnd.ms-office'];

    private const ZIP = ['application/zip', 'application/x-zip-compressed'];

    public const CONTENT = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'],
        'doc' => ['application/msword', ...self::OLE],
        'xls' => ['application/vnd.ms-excel', ...self::OLE],
        'msg' => ['application/vnd.ms-outlook', ...self::OLE],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', ...self::ZIP],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', ...self::ZIP],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'],
        'txt' => ['text/plain'],
        'eml' => ['message/rfc822', 'text/plain'],
    ];

    /** Largest file in KB for documents of this record type. */
    public static function maxKb(string $type): int
    {
        return in_array($type, self::USES_UPLOAD_LIMIT, true) ? \App\Support\UploadLimit::kb() : self::MAX_KB;
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Upload PO on a sales order: PDF, JPG or PNG only (S7).
        $mimes = $this->route('type') === 'sales_order' ? 'pdf,jpg,jpeg,png' : self::MIMES;

        return [
            'category' => ['required', Rule::in(\App\Services\DocumentService::categories((string) $this->route('type')))],
            // The extension must be allowed, and the content must be what that extension says (full test B4: a text
            // file renamed to .pdf is refused). Old Office files and Outlook emails are OLE containers that PHP often
            // reports only as "CDFV2", so each extension has its own list of content types (CRM upload check 18/10/2026).
            'file' => ['required', 'file', 'max:'.self::maxKb((string) $this->route('type')), 'extensions:'.$mimes, self::contentRule($mimes)],
        ];
    }

    /**
     * The content check of an uploaded file, shared with the documents chosen on a new PO (Fri 23/10/2026): the file
     * must not be empty and its content must match its extension. A disallowed extension is left to the extensions rule.
     */
    public static function contentRule(string $mimes = self::MIMES): \Closure
    {
        return function ($attr, $file, $fail) use ($mimes) {
            $ext = strtolower((string) $file->getClientOriginalExtension());
            $mime = strtolower((string) $file->getMimeType());
            $allowed = self::CONTENT[$ext] ?? null;
            if (! in_array($ext, explode(',', $mimes), true) || $allowed === null) {
                return; // the extensions rule reports it
            }
            if ($file->getSize() === 0 || $mime === 'application/x-empty') {
                $fail('The file is empty.');
            } elseif (! in_array($mime, $allowed, true)) {
                $fail('This file is not a real '.strtoupper($ext).' file (its content is '.($mime ?: 'unknown').'). Save it as '.strtoupper($ext).' and upload it again.');
            }
        };
    }

    public function messages(): array
    {
        return [
            'file.max' => in_array((string) $this->route('type'), self::USES_UPLOAD_LIMIT, true) ? \App\Support\UploadLimit::message() : 'The file is larger than 10 MB.',
            'file.extensions' => 'Upload a PDF, image (JPG, PNG, WEBP), Word, Excel, CSV, text or email file.',
            'category.in' => 'Choose what kind of document this is.',
        ];
    }

    public function attributes(): array
    {
        return ['category' => 'document type'];
    }
}
