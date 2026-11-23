<?php

use App\Http\Controllers\Amc\AmcClientController;
use App\Http\Controllers\Amc\AmcContractController;
use App\Http\Controllers\Amc\AmcDashboardController;
use App\Http\Controllers\Amc\AmcEmployeeController;
use App\Http\Controllers\Amc\AmcLookupController;
use App\Http\Controllers\Amc\AmcMasterController;
use App\Http\Controllers\Amc\AmcReturnController;
use Illuminate\Support\Facades\Route;

/*
| AMC module (21/11/2026), loaded by App\Providers\AmcServiceProvider with the same middleware as every other page.
| Every route checks its AMC right; Admin passes every check.
*/
Route::middleware(['web', 'auth', 'active'])->prefix('amc')->name('amc.')->group(function () {
    Route::get('dashboard', AmcDashboardController::class)->name('dashboard')->middleware('permission:amc-dashboard.view');

    // (22/11/2026) Masters page: the AMC masters the user may view, with their counts.
    Route::get('masters', [AmcMasterController::class, 'home'])->name('masters.home')->middleware('can:amc-masters.view');

    // Simple masters: each its own route names (amc.masters.<key>.*), one shared controller and screen.
    foreach (config('amc.masters') as $key => $cfg) {
        $p = $cfg['perm'];
        Route::prefix('masters/'.$key)->name('masters.'.$key.'.')->group(function () use ($key, $p) {
            Route::get('/', [AmcMasterController::class, 'show'])->name('index')->defaults('master', $key)->middleware("permission:$p.view");
            Route::post('/', [AmcMasterController::class, 'store'])->name('store')->defaults('master', $key)->middleware("permission:$p.add");
            Route::put('{id}', [AmcMasterController::class, 'update'])->name('update')->defaults('master', $key)->whereNumber('id')->middleware("permission:$p.edit");
            Route::delete('{id}', [AmcMasterController::class, 'destroy'])->name('destroy')->defaults('master', $key)->whereNumber('id')->middleware("permission:$p.delete");
        });
    }

    $e = AmcEmployeeController::class;
    Route::get('employees', [$e, 'index'])->name('employees.index')->middleware('permission:amc-employee.view');
    Route::get('employees/create', [$e, 'create'])->name('employees.create')->middleware('permission:amc-employee.add');
    Route::post('employees', [$e, 'store'])->name('employees.store')->middleware('permission:amc-employee.add');
    Route::get('employees/{employee}', [$e, 'edit'])->name('employees.edit')->middleware('permission:amc-employee.view');
    Route::put('employees/{employee}', [$e, 'update'])->name('employees.update')->middleware('permission:amc-employee.edit');
    Route::delete('employees/{employee}', [$e, 'destroy'])->name('employees.destroy')->middleware('permission:amc-employee.delete');
    // The photo: replaced from the Employee master or the contract page, shown wherever the employee is.
    Route::post('employees/{employee}/photo', [$e, 'photo'])->name('employees.photo')->middleware(['permission:amc-employee.edit|amc-contract.edit', 'throttle:uploads']);
    Route::get('employees/{employee}/photo', [$e, 'showPhoto'])->name('employees.photo.show')->middleware('permission:amc-employee.view|amc-contract.view');

    $c = AmcClientController::class;
    Route::get('clients', [$c, 'index'])->name('clients.index')->middleware('permission:amc-client.view');
    Route::get('clients/create', [$c, 'create'])->name('clients.create')->middleware('permission:amc-client.add');
    Route::get('clients/export', [$c, 'export'])->name('clients.export')->middleware(['permission:amc-client.view', 'throttle:downloads']);
    Route::get('clients/import-template', [$c, 'template'])->name('clients.template')->middleware(['permission:amc-client-import.add', 'throttle:downloads']);
    Route::post('clients/import', [$c, 'import'])->name('clients.import')->middleware(['permission:amc-client-import.add', 'throttle:downloads']);
    Route::post('clients', [$c, 'store'])->name('clients.store')->middleware('permission:amc-client.add');
    Route::get('clients/{client}', [$c, 'edit'])->name('clients.edit')->middleware('permission:amc-client.view');
    Route::put('clients/{client}', [$c, 'update'])->name('clients.update')->middleware('permission:amc-client.edit');
    // A client's segment, lines, AMC type and gardener for the contract and Return & Replace pages.
    Route::get('clients/{client}/info', [$c, 'info'])->name('clients.info')->middleware('permission:amc-client.view|amc-contract.view|amc-return.view');

    // Type-ahead pickers.
    Route::get('lookup/clients', [AmcLookupController::class, 'clients'])->name('lookup.clients')->middleware('permission:amc-contract.view|amc-return.view|amc-client.view');
    Route::get('lookup/parties', [AmcLookupController::class, 'parties'])->name('lookup.parties')->middleware('permission:amc-client.add|amc-client.edit');
    Route::get('lookup/employees', [AmcLookupController::class, 'employees'])->name('lookup.employees')->middleware('permission:amc-contract.view|amc-return.view|amc-employee.view|amc-client.add|amc-client.edit');

    // Customer PO / AMC (amc.contract.*: the entry page) and the AMC list (amc.contracts.*), so the menu marks the right one.
    $k = AmcContractController::class;
    Route::get('contracts/list', [$k, 'list'])->name('contracts.list')->middleware('permission:amc-contract.view');
    Route::get('contracts/export', [$k, 'export'])->name('contracts.export')->middleware(['permission:amc-contract.view', 'throttle:downloads']);
    Route::post('contracts/purge-images', [$k, 'purge'])->name('contracts.purge')->middleware('permission:amc-contract.delete');
    Route::get('contract', [$k, 'index'])->name('contract.index')->middleware('permission:amc-contract.view');
    Route::get('contract/create', [$k, 'create'])->name('contract.create')->middleware('permission:amc-contract.add');
    Route::post('contract', [$k, 'store'])->name('contract.store')->middleware('permission:amc-contract.add');
    Route::get('contract/{contract}', [$k, 'show'])->name('contract.show')->middleware('permission:amc-contract.view');
    Route::put('contract/{contract}', [$k, 'update'])->name('contract.update')->middleware('permission:amc-contract.edit');
    Route::delete('contract/{contract}', [$k, 'destroy'])->name('contract.destroy')->middleware('permission:amc-contract.delete');
    Route::post('contract/{contract}/files', [$k, 'upload'])->name('contract.files.store')->middleware(['permission:amc-contract.edit', 'throttle:uploads']);
    Route::get('files/{file}', [$k, 'download'])->name('contract.files.show')->middleware('permission:amc-contract.view');
    Route::delete('files/{file}', [$k, 'destroyFile'])->name('contract.files.destroy')->middleware('permission:amc-contract.delete');

    $r = AmcReturnController::class;
    Route::get('returns', [$r, 'index'])->name('returns.index')->middleware('permission:amc-return.view');
    Route::get('returns/export', [$r, 'export'])->name('returns.export')->middleware(['permission:amc-return.view', 'throttle:downloads']);
    Route::get('returns/history/{client}', [$r, 'history'])->name('returns.history')->middleware('permission:amc-return.view');
    Route::get('returns/create', [$r, 'create'])->name('returns.create')->middleware('permission:amc-return.add');
    Route::post('returns', [$r, 'store'])->name('returns.store')->middleware('permission:amc-return.add');
    Route::get('returns/{return}', [$r, 'edit'])->name('returns.edit')->middleware('permission:amc-return.view');
    Route::put('returns/{return}', [$r, 'update'])->name('returns.update')->middleware('permission:amc-return.edit');
    Route::delete('returns/{return}', [$r, 'destroy'])->name('returns.destroy')->middleware('permission:amc-return.delete');
});

// (22/11/2026, test fix) The AMC Report under Reports: the same page as amc.contracts.list, named reports.* like the
// CRM and Sales reports so the menu marks Reports when it is opened from there, and AMC when opened with ?in=amc.
Route::middleware(['web', 'auth', 'active'])->get('reports/amc/amc-report', [AmcContractController::class, 'list'])
    ->name('reports.amc.report')->middleware('permission:amc-contract.view');
