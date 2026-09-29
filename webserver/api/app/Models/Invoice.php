<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Facture émise du temps des anciens abonnements (lecture seule). */
class Invoice extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }
}
