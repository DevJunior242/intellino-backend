<?php

namespace App\Models;

use App\Models\League;
use App\Models\Category;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Disciplineleague;
use App\Models\NiveauxCompetition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Competition extends Model
{
    use HasUuids;
    protected $table = 'competitions';
    protected $fillable = ['category_id', 'disciplineleague_id', 'niveau_id', 'evenement_id', 'status', 'heure_debut_prevu', 'heure_fin_prevue', 'id'];

    protected $keyType = 'string';
    public $incrementing = false;
    const STATUT_ATTENTE = 0;
    const STATUT_EN_COURS = 1;
    const STATUT_TERMINE = 2;
    const STATUT_PAUSE = 3;


    public function league()
    {
        return $this->belongsTo(League::class);
    }

    public function niveau()
    {
        return $this->belongsTo(NiveauxCompetition::class, 'niveau_id');
    }


    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    //inscriptions
    public function inscriptions()
    {
        return $this->hasMany(Inscription::class);
    }

    public function discipline()
    {
        return $this->belongsTo(Disciplineleague::class, 'disciplineleague_id');
    }

    public function evenement()
    {
        return $this->belongsTo(Evenement::class, 'evenement_id');
    }
}
