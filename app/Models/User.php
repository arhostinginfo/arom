<?php

namespace App\Models;

use App\Support\Roles;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'mobile', 'password', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class)->orderBy('code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** $lock: lock the active Admin rows until the transaction ends, so two changes cannot both remove the last one (A7). */
    public static function activeAdminCount(bool $lock = false): int
    {
        return static::role(Roles::ADMIN)->where('status', UserStatus::Active)
            ->when($lock, fn ($q) => $q->lockForUpdate())->pluck('users.id')->count();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Roles::ADMIN);
    }

    public function roleName(): string
    {
        return $this->roles->first()?->name ?? 'No role';
    }

    public function initials(): string
    {
        $parts = preg_split('/[\s.]+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY);

        return strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2))));
    }

    // Stored as 10 digits; shown as +91 98220 00001.
    public function mobileDisplay(): string
    {
        return strlen($this->mobile) === 10
            ? '+91 '.substr($this->mobile, 0, 5).' '.substr($this->mobile, 5)
            : (string) $this->mobile;
    }
}
