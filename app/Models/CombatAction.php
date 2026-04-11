<?php

namespace App\Models;

use App\Models\Combat;
use App\Models\Inscription;
use Illuminate\Database\Eloquent\Model;

class CombatAction extends Model
{
    protected $table = 'combat_actions';
    protected $fillable = [
        'combat_id',
        'inscription_id',
        'type',
        'valeur',
        'temps_match',
    ];
    protected $keyType = 'string';
    public $incrementing = false;
    const TYPE_POINT = 0;
    const TYPE_PENALITE = 1;


    public function combat()
    {
        return $this->belongsTo(Combat::class);
    }

    public function inscription()
    {
        return $this->belongsTo(Inscription::class);
    }
    // Accesseur pour récupérer le nom du type facilement
    public function getTypeLabelAttribute()
    {
        return match ($this->type) {
            self::TYPE_POINT => 'Point',
            self::TYPE_PENALITE => 'Penalité',
            default => 'Inconnu',
        };
    }
}
