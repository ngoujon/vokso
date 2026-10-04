<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Podcast généré (statut "on" = publié). */
class Generation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'generation_id', 'slug', 'title', 'text_content', 'image_url', 'audio_url', 'idcategorie',
        'user_id', 'cost_text', 'cost_image', 'cost_audio', 'cost_total',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('generations.statut', 'on');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'idcategorie', 'idcategorie');
    }
}
