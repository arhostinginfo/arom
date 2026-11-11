<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Admin > Page setup as a view sees it (PageSetupService::form): whether a field shows, whether it is mandatory,
 * its red star and the "Mandatory: …" line under the form. Fields not in the page setup always show, optional.
 */
class PageForm
{
    public function __construct(private readonly array $fields)
    {
    }

    public function show(string $field): bool
    {
        return $this->fields[$field]['visible'] ?? true;
    }

    public function req(string $field): bool
    {
        return $this->fields[$field]['required'] ?? false;
    }

    public function star(string $field): HtmlString
    {
        return new HtmlString($this->req($field) ? '<span class="req">*</span>' : '');
    }

    /** "Mandatory: Client (enquiry), Visit date and Status." */
    public function mandatory(): string
    {
        $labels = array_values(array_map(fn ($f) => $f['label'], array_filter($this->fields, fn ($f) => $f['required'])));
        $last = array_pop($labels);

        return $last === null ? '' : 'Mandatory: '.($labels ? implode(', ', $labels).' and '.$last : $last).'.';
    }
}
