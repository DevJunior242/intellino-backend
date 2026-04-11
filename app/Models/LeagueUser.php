<?php

namespace App\Models;

use App\Models\Role;
use App\Models\User;
use App\Models\League;
use Illuminate\Database\Eloquent\Model;

class LeagueUser extends Model
{
    protected $fillable = [
        'league_id',
        'user_id',
        'role_id',
    ];

    public function league()
    {
        return $this->belongsTo(League::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
