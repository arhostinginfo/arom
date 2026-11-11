<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Admin > Config (22/10/2026): one installation-wide setting, e.g. upload_max_kb. Defaults in config('nursery.settings').
class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];
}
