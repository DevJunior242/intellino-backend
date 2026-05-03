<?php

namespace App\Models;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Role extends Model
{
    use HasUuids;
    protected $fillable = ['name'];
    protected $keyType = 'string';
    public $incrementing = false;

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public static function clubAdminRoles()
    {
        return self::whereIn('name', [
            'admin_club',
            'instructeur'
        ])->pluck('id');
    }
    public static function leagueAdminRoles()
    {
        return self::whereIn('name', [
            'admin_league',
            'arbitre_league',
        ])->pluck('id');
    }

    public static function studentRoles()
    {
        return self::whereIn('name', [
            'karateka',
        ])->pluck('id');
    }
}
