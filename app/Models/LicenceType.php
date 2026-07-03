<?php

namespace App\Models;

use App\Models\Saison;
use App\Models\Licence;
use App\Models\Federation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LicenceType extends Model
{
    use HasUuids;

    protected $fillable = [
        'saison_id',
        'federation_id',
        'code',
        'nom',
        'tarif',
    ];

    protected $casts = [
        'tarif' => 'decimal:2',
    ];

    public function saison()
    {
        return $this->belongsTo(Saison::class);
    }

    public function federation()
    {
        return $this->belongsTo(Federation::class);
    }

    public function licences()
    {
        return $this->hasMany(Licence::class);
    }
}
