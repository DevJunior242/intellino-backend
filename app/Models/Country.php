<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Federation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasUuids;
    protected $fillable = ['name'];
    protected $keyType = 'string';
    public $incrementing = false;

    public function clubs()
    {
        return $this->hasMany(Club::class);
    }

    public function federations(): HasMany
    {
        return $this->hasMany(Federation::class);
    }
}
