<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfigRequest;
use App\Services\AppSettings;
use App\Support\UploadLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin > Config (22/10/2026): installation-wide settings. Now the maximum size of a file upload on a form
 * (default 1 MB). View needs Config · View, saving needs Config · Edit (Admin only by default).
 */
class ConfigController extends Controller
{
    public function edit(): View
    {
        return view('admin.config.edit', [
            'uploadKb' => UploadLimit::kb(),
            'php' => UploadLimit::phpLimits(),
        ]);
    }

    public function update(ConfigRequest $request, AppSettings $settings): RedirectResponse
    {
        $kb = (int) round((float) $request->validated('upload_max_mb') * 1024);
        $settings->save([UploadLimit::KEY => $kb], $request->user(), fn ($v, $key) => $key === UploadLimit::KEY && $v !== null ? UploadLimit::label((int) $v) : $v);

        return redirect()->route('admin.config.edit')->with('toast', 'Config saved · uploads up to '.UploadLimit::label($kb));
    }
}
