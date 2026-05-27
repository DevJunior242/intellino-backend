<?php

namespace App\Models;

use App\Models\Combat;
use App\Models\Inscription;
use App\Models\RotationArbitre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CombatAction extends Model
{
    use HasUuids;
    protected $table = 'combat_actions';
    protected $fillable = [
        'combat_id',
        'rotation_arbitre_id',
        'type',
        'valeur',
        'combattant',
        'signale_a',
        'validee',
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

    public function rotationArbitre()
    {
        return $this->belongsTo(RotationArbitre::class);
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
