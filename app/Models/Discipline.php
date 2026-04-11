<?php

namespace App\Models;

use App\Models\Club;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class Discipline extends Model
{
    use HasUuids;
    protected $table = 'disciplines';
    protected $fillable = [
        'name',
        'description',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function clubs()
    {
        return $this->hasMany(Club::class);
    }
}
