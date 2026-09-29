<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $email
 * @property string $password_hash
 * @property string $role user|admin
 * @property string $status active|disabled
 * @property bool $must_change_password
 * @property ?string $totp_secret
 * @property bool $totp_enabled
 */
class User extends Authenticatable
{
    public const UPDATED_AT = null;

    protected $fillable = ['email', 'password_hash', 'role', 'status', 'must_change_password'];

    protected $hidden = ['password_hash', 'totp_secret'];

    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
            'totp_enabled' => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Représentation renvoyée au front (auth-me, login). */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'must_change_password' => (bool) $this->must_change_password,
            'totp_enabled' => (bool) $this->totp_enabled,
        ];
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }
}
