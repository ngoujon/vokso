<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public $timestamps = false;

    protected $table = 'categorie';
    protected $primaryKey = 'idcategorie';

    protected $fillable = ['label', 'icon', 'cover_image'];
}
