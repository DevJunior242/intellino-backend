<?php

namespace App\Models;

use App\Models\Poule;
use App\Models\Competition;
use App\Models\Inscription;
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
        'statut',
        'score_final_aka',
        'score_final_ao',
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
}
