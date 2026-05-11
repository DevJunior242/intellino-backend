<?php

namespace App\Models;

use App\Models\Note;
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
    ];
    protected $keyType = 'string';
    public $incrementing = false;

    const STATUS_NOT_STARTED = 0;
    const STATUS_STARTED = 1;
    const STATUS_FINISHED = 2;

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

    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            self::STATUS_NOT_STARTED => 'En attente',
            self::STATUS_STARTED => 'En cours',
            self::STATUS_FINISHED => 'Terminé',
            default => 'Inconnu',
        };
    }
}
