<?php

namespace App\Models;

use App\Models\Inscription;
use App\Models\ConfigNotation;
use App\Models\PouleInscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Poule extends Model
{
    use HasUuids;
    protected $table = 'poules';
    protected $fillable = [
        'config_notation_id',
        'nom',
        'etape',
        'status',
        'ordre',
    ];
    const STATUS_DRAFT = 0;
    const STATUS_STARTED = 1;
    const STATUS_FINISHED = 2;
    protected $keyType = 'string';

    public function configNotation()
    {
        return $this->belongsTo(ConfigNotation::class);
    }

    public function combattants()
    {
        return $this->belongsToMany(Inscription::class, 'poule_inscriptions', 'poule_id', 'inscription_id')
            ->using(PouleInscription::class)
            ->withPivot('points_victoire', 'total_points_marques', 'total_points_encaisses');
    }

    public function getStatusAttribute($value)
    {
        return match ($value) {
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_STARTED => 'En cours',
            self::STATUS_FINISHED => 'Terminé',
            default => $value,
        };
    }
}
