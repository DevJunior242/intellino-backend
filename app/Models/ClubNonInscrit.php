<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ClubNonInscrit extends Model
{
    use HasUuids;
    protected $table = 'club_non_inscrits';
    protected $fillable = [
        'name',
        'description',
        'organisateur_id',
        'organisateur_type',
    ];
    public function organisateur()
    {
        return $this->morphTo();
    }

    public $incrementing = false;
    public $keyType = 'string';
}
