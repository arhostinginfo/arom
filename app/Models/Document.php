<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// Documents & attachments: keeps the uploader, upload time and the record it belongs to.
class Document extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['category', 'original_name', 'path', 'mime', 'size'];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, (int) round($this->size / 1024)).' KB';
    }

    public function activityLabel(): string
    {
        return $this->original_name;
    }
}
