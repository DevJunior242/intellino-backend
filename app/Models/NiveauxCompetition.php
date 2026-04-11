<?php

namespace App\Models;

use App\Models\Competition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class NiveauxCompetition extends Model
{
    use HasUuids;
    protected $table = 'niveaux_competitions';
    protected $fillable = ['nom', 'ville'];

    protected $keyType = 'string';
    public $incrementing = false;

    public function competitions()
    {
        return $this->hasMany(Competition::class);
    }
}
