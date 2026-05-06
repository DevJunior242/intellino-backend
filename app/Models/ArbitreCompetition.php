<?php

namespace App\Models;

use App\Models\User;
use App\Models\Competition;
use App\Models\RotationArbitre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ArbitreCompetition extends Model
{
    use HasUuids, Notifiable;

    protected $fillable = ['user_id', 'evenement_id', 'code_acces', 'connecte'];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'connecte' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function rotation()
    {
        return $this->hasOne(RotationArbitre::class);
    }
}
