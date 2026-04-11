<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JugeOption extends Model
{
    use HasUuids;
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'valeur',
        'libelle',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];
}
