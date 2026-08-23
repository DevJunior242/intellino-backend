<?php

namespace App\Models;

use App\Models\Role;
use App\Models\User;
use App\Models\League;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LeagueUser extends Pivot
{
    use HasUuids;

    protected $table = 'league_users';

    protected $fillable = [
        'league_id',
        'user_id',
        'role_id',
        'mandate_start_at',
        'mandate_end_at',
        'mandate_status',
    ];
    protected $keyType = 'string';
    public $incrementing = false;


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
