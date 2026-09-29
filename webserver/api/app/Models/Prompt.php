<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Prompts éditables en base (texte, image, titre, catégorie...). */
class Prompt extends Model
{
    public $timestamps = false;

    protected $table = 'prompt';
    protected $primaryKey = 'idprompt';

    public static function content(string $type): string
    {
        return (string) static::query()->where('type', $type)->value('content');
    }
}
