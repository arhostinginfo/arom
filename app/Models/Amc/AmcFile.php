<?php

namespace App\Models\Amc;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A file of an AMC contract on the private disk: PO / agreement, delivery challan document or image, deployment image.
class AmcFile extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'amc_files';

    protected $fillable = ['category', 'path', 'original_name', 'mime', 'size'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(AmcContract::class, 'amc_contract_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function categoryLabel(): string
    {
        return config('amc.file_categories.'.$this->category.'.label', $this->category);
    }

    /** "245 KB" or "1.2 MB". */
    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, (int) round($this->size / 1024)).' KB';
    }
}
