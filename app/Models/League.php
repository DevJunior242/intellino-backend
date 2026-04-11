<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class League extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'logo',
    ];

    protected $keyType = 'string';
    public $timestamps = false;

    public function users()
    {
        return $this->belongsToMany(User::class, 'league_users')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'league_users')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function clubs()
    {
        return $this->hasMany(Club::class);
    }

    // public function members()
    // {
    //     return $this->belongsToMany(User::class, 'league_user')
    //         ->withPivot('role_id')
    //         ->withTimestamps();
    // }

    public function arbitres()
    {
        return $this->belongsToMany(User::class, 'league_user')
            ->withPivot('role_id')
            ->wherePivotIn('role_id', function ($query) {
                $query->select('id')
                    ->from('roles')
                    ->where('name', 'arbitre_league');
            });
    }
}
