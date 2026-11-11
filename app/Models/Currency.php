<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = ['country', 'code', 'symbol'];

    public static function defaultId(): ?int
    {
        return static::where('code', 'INR')->value('id');
    }
}
