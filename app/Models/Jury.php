<?php

namespace App\Models;

use App\Models\User;
use App\Models\Mandat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Jury extends Model
{
    use HasUuids;
    protected $table = 'juries';
    protected $fillable = ['mandat_id', 'user_id', 'role_jury', 'a_valide', 'date_validation', 'organisateur_id', 'organisateur_type'];
    protected $keyType = 'string';
    public $incrementing = false;

    public function mandat()
    {
        return $this->belongsTo(Mandat::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organisateur()
    {
        return $this->morphTo();
    }
}
