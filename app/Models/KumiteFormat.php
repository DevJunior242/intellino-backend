<?php

namespace App\Models;

use App\Models\ConfigNotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class KumiteFormat extends Model
{
    use HasUuids;
    protected $table = 'kumite_formats';
    protected $fillable = ['code', 'libelle', 'description', 'actif', 'ordre'];
    protected $keyType = 'string';
    public $incrementing = false;


    public function configs()
    {
        return $this->hasMany(ConfigNotation::class);
    }
}
