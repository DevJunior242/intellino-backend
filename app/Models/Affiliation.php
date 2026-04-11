<?php

namespace App\Models;

use App\Models\Club;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Affiliation extends Model
{
    use HasUuids;
    protected $fillable = [
        'league_id',
        'club_id',
        'saison',
        'cotisation',
        'date_affiliation',
        'statut',
    ];
    protected $keyType = 'string';
    public $incrementing = false;

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
