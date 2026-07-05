<?php

namespace App\Models;

use App\Models\Competition;
use App\Models\ArbitreCompetition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Evenement extends Model
{
    use HasUuids;
    protected $table = 'evenements';
    protected $fillable = [
        'nom',
        'organisateur_id',
        'organisateur_type',
        'saison_id',
        'lieu',
        'date_debut',
        'date_fin',

        'status',
    ];
    const STATUT_EN_ATTENTE = 0;
    const STATUT_EN_COURS = 1;
    const STATUT_TERMINE = 2;
    protected $keyType = 'string';
    public $incrementing = false;

    public function organisateur()
    {
        return $this->morphTo();
    }

    public function competitions()
    {
        return $this->hasMany(Competition::class);
    }

    public function arbitreCompetitions()
    {
        return $this->hasMany(ArbitreCompetition::class);
    }



    // Accessor pour récupérer le nom du status facilement
    public function getStatutLabelAttribute()
    {
        return match ($this->status) {
            self::STATUT_EN_ATTENTE => 'En attente',
            self::STATUT_EN_COURS  => 'En cours',
            self::STATUT_TERMINE   => 'Terminée',
            default                => 'Inconnu',
        };
    }
}
