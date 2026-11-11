<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyRequest;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Admin › Masters › Company (D21, S6): the company's name, GSTIN and contact details printed on tax invoices. */
class CompanyController extends Controller
{
    public function edit(): View
    {
        $company = Company::current();

        return view('admin.company.edit', ['company' => $company, 'history' => ActivityLog::history($company)]);
    }

    public function update(CompanyRequest $request, CompanyService $companies): RedirectResponse
    {
        // Company logo for the quotation PDF (23/10/2026): a new file replaces it, "Remove logo" drops it.
        $companies->save($request->validated(), $request->user(), $request->file('logo'), $request->boolean('remove_logo'));

        return redirect()->route('admin.company.edit')->with('toast', 'Company details saved');
    }
}
