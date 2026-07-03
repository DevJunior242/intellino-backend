<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;


class PouleInscription extends Pivot
{
    protected $keyType = 'string';
    public $incrementing = false;

    // Nom de ta table pivot en base de données
    protected $table = 'poule_inscriptions';

    // Les champs modifiables sur ton pivot
    protected $fillable = [
        'id',
        'poule_id',
        'inscription_id',
        'points_victoire',
        'total_points_marques',
        'total_points_encaisses',
    ];
}
