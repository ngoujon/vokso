<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeton de connexion. Seule l'empreinte SHA-256 du jeton est stockée : une
 * fuite de la base ne permet pas de se connecter à la place des utilisateurs.
 */
class AuthToken extends Model
{
    public const UPDATED_AT = null;

    protected $primaryKey = 'token';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['token', 'user_id', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
