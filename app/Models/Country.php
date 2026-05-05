<?php

namespace App\Models;

use App\Models\Club;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

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
}
