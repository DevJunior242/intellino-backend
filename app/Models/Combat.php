<?php

namespace App\Models;

use App\Models\Poule;
use App\Models\Competition;
use App\Models\Inscription;
use App\Models\CombatAction;
use App\Models\ConfigNotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Combat extends Model
{
    use HasUuids;
    protected $fillable = [
        'id',
        'config_notation_id',
        'poule_id',
        'inscription_aka_id',
        'inscription_ao_id',
        'etape',
        'ordre',
        'status',
        'score_final_aka',
        'score_final_ao',
        'temps_ecoule',
        'hajime_at',
        'yame_at',
        'source_aka_combat_id',
        'source_ao_combat_id',
        'next_combat_id',
        'senshu_id',
        'vainqueur_id',
        'type_victoire',
        'type',
        'valeur',
    ];



    public function poule()
    {
        return $this->belongsTo(Poule::class);
    }
    public function configNotation()
    {
        return $this->belongsTo(ConfigNotation::class);
    }
    public function inscriptionAka()
    {
        return $this->belongsTo(Inscription::class, 'inscription_aka_id');
    }
    public function inscriptionAo()
    {
        return $this->belongsTo(Inscription::class, 'inscription_ao_id');
    }

    public function actions()
    {
        return $this->hasMany(CombatAction::class);
    }
}
