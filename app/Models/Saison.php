<?php

namespace App\Models;

use App\Models\League;
use App\Models\Disciplineleague;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\Fluent\Concerns\Has;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Saison extends Model
{
    use HasUuids;
    protected $fillable = ['libele', 'dateDebut', 'dateFin', 'active'];
    protected $keyType = 'string';
    public $incrementing = false;

    public function leagues()
    {
        return $this->hasMany(League::class);
    }

    public function disciplines()
    {
        return $this->hasMany(Disciplineleague::class);
    }
    public function categories()
    {
        return $this->hasMany(Category::class);
    }
}
