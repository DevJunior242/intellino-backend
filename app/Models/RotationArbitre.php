<?php

namespace App\Models;

use App\Models\User;
use App\Models\CombatAction;
use App\Models\Competition;
use App\Models\ConfigNotation;
use App\Models\ArbitreCompetition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RotationArbitre extends Model
{
    use HasUuids;

    protected $fillable = [
        'config_notation_id',
        'arbitre_competition_id',
        'ordre',
        'actif',
        'poste',
        'nb_passages',
        'est_superviseur',
    ];
    protected $keyType = 'string';
    public $incrementing = false;
    protected $casts = [
        'actif' => 'boolean',
    ];


    public function arbitreCompetition()
    {
        return $this->belongsTo(ArbitreCompetition::class);
    }

    // Accès direct au user via la relation
    public function user()
    {
        return $this->hasOneThrough(
            User::class,
            ArbitreCompetition::class,
            'id',          // FK sur arbitre_competitions
            'id',          // FK sur users
            'arbitre_competition_id',
            'user_id'
        );
    }

    // Helpers
    public function estActif(): bool
    {
        return $this->actif;
    }

    public function estAuBanc(): bool
    {
        return !$this->actif;
    }
    public function configNotation()
    {
        return $this->belongsTo(ConfigNotation::class);
    }

    public function combatActions()
    {
        return $this->hasMany(CombatAction::class);
    }
}
