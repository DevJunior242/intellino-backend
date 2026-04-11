<?php

namespace App\Models;

use App\Models\User;
use App\Models\Poste;
use App\Models\Mandat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Candidat extends Model
{
    use HasUuids;
    protected $table = 'candidats';
    protected $fillable = ['mandat_id', 'poste_id', 'user_id', 'est_elu'];
    protected $keyType = 'string';
    public $incrementing = false;

    public function mandat()
    {
        return $this->belongsTo(Mandat::class, 'mandat_id');
    }

    public function poste()
    {
        return $this->belongsTo(Poste::class, 'poste_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
