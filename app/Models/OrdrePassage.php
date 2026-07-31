<?php

namespace App\Models;

use App\Models\Kata;
use App\Models\Note;
use App\Models\Combat;
use App\Models\Inscription;
use App\Models\ConfigNotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrdrePassage extends Model
{
    use HasUuids;
    protected $fillable = [
        'config_notation_id',
        'ordre',
        'status',
        'score_final',
        'inscription_id',
        'kata_id',
        'combat_id',
        'phase',
        'disqualifie_bunkai',
    ];
    protected $casts = [
        'disqualifie_bunkai' => 'boolean',
    ];
    protected $keyType = 'string';
    public $incrementing = false;

    const STATUS_NOT_STARTED = 0;
    const STATUS_STARTED = 1;
    const STATUS_FINISHED = 2;
    // Athlète absent à l'appel ou qui abandonne : disqualifié de la
    // catégorie (WKF Kata Competition Rules, Art. 6.4).
    const STATUS_KIKEN = 3;

    public function inscription()
    {
        return $this->belongsTo(Inscription::class);
    }
    public function configNotation()
    {
        return $this->belongsTo(ConfigNotation::class);
    }
    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function kata()
    {
        return $this->belongsTo(Kata::class);
    }

    public function combat()
    {
        return $this->belongsTo(Combat::class);
    }

    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            self::STATUS_NOT_STARTED => 'En attente',
            self::STATUS_STARTED => 'En cours',
            self::STATUS_FINISHED => 'Terminé',
            self::STATUS_KIKEN => 'Absent (Kiken)',
            default => 'Inconnu',
        };
    }
}
