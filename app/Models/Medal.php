<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Medal extends Model
{
    use HasUuids;
    protected $table = 'medals';
    protected $fillable = [
        'name',
        'description',
        'club_id',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
}
