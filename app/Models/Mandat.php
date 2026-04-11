<?php

namespace App\Models;

use App\Models\Candidat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Mandat extends Model
{
    use HasUuids;
    protected $table = 'mandats';
    const STATUS_ATTENTE = 0;
    const STATUS_TERMINE = 1;
    const STATUS_ACTIVE = 2;
    protected $fillable = ['start_at', 'end_at', 'actif'];
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function candidates()
    {
        return $this->hasMany(Candidat::class);
    }
    public function getStatutTexteAttribute()
    {
        return match ($this->statut) {
            self::STATUS_ATTENTE => 'En attente',
            self::STATUS_TERMINE  => 'Terminée',
            self::STATUS_ACTIVE   => 'Actif',
            default => 'Inconnu',
        };
    }
}
