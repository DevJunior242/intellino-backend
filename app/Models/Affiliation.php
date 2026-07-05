<?php

namespace App\Models;

use App\Models\Saison;
use App\Models\Federation;
use App\Models\AffiliationPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Le tarif d'affiliation fixé par une Fédération pour une saison donnée.
 * Une seule ligne par (federation_id, saison_id) : voir AffiliationPayment
 * pour le suivi, club par club, du paiement de ce tarif.
 */
class Affiliation extends Model
{
    use HasUuids;
    protected $fillable = [
        'federation_id',
        'saison_id',
        'cotisation',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    public function federation()
    {
        return $this->belongsTo(Federation::class);
    }

    public function saison()
    {
        return $this->belongsTo(Saison::class);
    }

    public function payments()
    {
        return $this->hasMany(AffiliationPayment::class);
    }
}
