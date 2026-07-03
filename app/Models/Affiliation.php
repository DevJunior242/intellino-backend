<?php

namespace App\Models;

use App\Models\Club;
use App\Models\League;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Affiliation extends Model
{
    use HasUuids;
    protected $fillable = [
        'league_id',
        'club_id',
        'saison_id',
        'cotisation',
        'status',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    const STATUS_EN_ATTENTE = 0;
    const STATUS_EN_ACTIF = 1;
    const STATUS_EN_EXPIRE = 2;
    const STATUS_SUSPENDU = 3;

    public function league()
    {
        return $this->belongsTo(League::class);
    }
    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            self::STATUS_EN_ATTENTE => 'En attente',
            self::STATUS_EN_ACTIF => 'Active',
            self::STATUS_EN_EXPIRE => 'Expirée',
            self::STATUS_SUSPENDU => 'Suspendue',
            default => 'Inconnu',
        };
    }
}
