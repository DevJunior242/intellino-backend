<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Kata extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['nom', 'niveau', 'actif'];

    public function inscriptions()
    {
        return $this->hasMany(Inscription::class);
    }
}
