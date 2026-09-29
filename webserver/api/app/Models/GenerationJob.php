<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Suivi d'une génération asynchrone (voir ProcessGenerationJob). */
class GenerationJob extends Model
{
    protected $primaryKey = 'job_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'job_id', 'user_id', 'source_type', 'status', 'step', 'progress', 'input', 'audio_path',
        'generation_id', 'error_message',
    ];

    protected function casts(): array
    {
        return ['progress' => 'integer'];
    }
}
