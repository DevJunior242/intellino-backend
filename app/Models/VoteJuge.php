<?php

namespace App\Models;

use App\Models\Combat;
use App\Models\Inscription;
use App\Models\ArbitreCompetition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class VoteJuge extends Model
{
    use HasUuids;
    protected $table = 'vote_juges';
    protected $fillable = [
        'combat_id',
        'arbitre_competition_id',
        'inscription_id',
        'point_demande',
        'clique_a',
        'valide',
    ];
    protected $keyType = 'string';
    public $incrementing = false;
    const POINT_YUKO = 'Yuko';
    const POINT_WAZA_ARI = 'Waza-ari';
    const POINT_IPPON = 'Ippon';

    public function combat()
    {
        return $this->belongsTo(Combat::class);
    }

    public function arbitreCompetition()
    {
        return $this->belongsTo(ArbitreCompetition::class);
    }

    public function inscription()
    {
        return $this->belongsTo(Inscription::class);
    }
    // Accesseur pour récupérer le nom du type facilement
    public function getPointDemandeLabelAttribute()
    {
        return match ($this->point_demande) {
            self::POINT_YUKO => 'Yuko',
            self::POINT_WAZA_ARI => 'Waza-ari',
            self::POINT_IPPON => 'Ippon',
            default => 'Inconnu',
        };
    }
}
