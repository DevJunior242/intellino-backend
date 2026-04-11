<?php

namespace App\Models;

use App\Models\Competition;
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
        'lieu',
        'date_debut',
        'date_fin',
        'statut',
    ];
    const STATUT_BROUILLON = 'brouillon';
    const STATUT_OUVERT = 'ouverte';
    const STATUT_EN_COURS = 'en_cours';
    const STATUT_CLOTURE = 'cloturee';
    const STATUT_TERMINE = 'terminee';
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



    // Accessor pour récupérer le nom du statut facilement
    public function getStatutLabelAttribute()
    {
        return match ($this->statut) {
            self::STATUT_BROUILLON => 'Brouillon',
            self::STATUT_OUVERT    => 'Ouvert',
            self::STATUT_EN_COURS  => 'En cours',
            self::STATUT_CLOTURE   => 'Cloture',
            self::STATUT_TERMINE   => 'Terminée',
            default                => 'Inconnu',
        };
    }
}
