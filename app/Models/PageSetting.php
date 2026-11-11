<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Admin > Page setup: whether a field of a page shows and whether it is mandatory.
class PageSetting extends Model
{
    protected $fillable = ['page', 'field', 'is_visible', 'is_required'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'is_required' => 'boolean'];
    }
}
