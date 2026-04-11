<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Activity extends Model
{
    use HasUuids;
    protected $fillable = [
        'user_id',
        'organisateur_id',
        'organisateur_type',
        'type',
        'action',
        'description',
    ];
    protected $keyType = 'string';
    public $incrementing = false;


    public function organisateur()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
