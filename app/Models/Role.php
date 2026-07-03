<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Role extends Model
{
    use HasUuids;
    protected $fillable = [
        'name',
        'display_name',
        'description'
    ];
    protected $keyType = 'string';
    public $incrementing = false;

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public static function AccessRoles()
    {
        return self::whereIn('name', [
            'admin',
            'instructeur',
        ])->pluck('id');
    }
    //clubAdminRoles
    public static function clubAdminRoles()
    {
        return self::whereIn('name', [
            'admin',
            'instructeur',
        ])->pluck('id');
    }
    public static function leagueAdminRoles()
    {
        return self::whereIn('name', [
            'admin',
            'arbitre',
        ])->pluck('id');
    }

    public static function studentRoles()
    {
        return self::whereIn('name', [
            'karateka',
        ])->pluck('id');
    }
}
