<?php

namespace App\Models;

use App\Models\Poule;
use App\Models\Competition;
use App\Models\Inscription;
use App\Models\CombatAction;
use App\Models\OrdrePassage;
use App\Models\ConfigNotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Combat;

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
    //constantes de statut
    const STATUS_EN_ATTENTE = 0;
    const STATUS_EN_COURS = 1;
    const STATUS_TERMINE = 2;
    const STATUS_HANTEI = 3;


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

    // Kata : les deux passages (Aka/Ao) notés par les juges pour ce combat
    public function ordrePassages()
    {
        return $this->hasMany(OrdrePassage::class);
    }

    public function ordrePassageAka()
    {
        return $this->hasOne(OrdrePassage::class)->where('inscription_id', $this->inscription_aka_id);
    }

    public function ordrePassageAo()
    {
        return $this->hasOne(OrdrePassage::class)->where('inscription_id', $this->inscription_ao_id);
    }

    public function nextCombat()
    {
        return $this->belongsTo(Combat::class, 'next_combat_id');
    }
    public function sourceAkaCombat()
    {
        return $this->belongsTo(Combat::class, 'source_aka_combat_id');
    }
    public function sourceAoCombat()
    {
        return $this->belongsTo(Combat::class, 'source_ao_combat_id');
    }
    public function getStatutLabelAttribute()
    {
        return match ($this->status) {
            self::STATUS_EN_ATTENTE => 'En attente',
            self::STATUS_EN_COURS  => 'En cours',
            self::STATUS_TERMINE   => 'Terminée',
            self::STATUS_HANTEI    => 'Hantei',
            default                => 'Inconnu',
        };
    }
}
