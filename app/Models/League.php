<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Role;
use App\Models\User;
use App\Models\Federation;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class League extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'country_id',
        'region',
        'address',
        'logo',
        'region',
        'invitation_code',
        'federation_id',
    ];

    protected $keyType = 'string';
    public $incrementing = false;
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
    public function federation()
    {
        return $this->belongsTo(Federation::class);
    }

    // public function members()
    // {
    //     return $this->belongsToMany(User::class, 'league_user')
    //         ->withPivot('role_id')
    //         ->withTimestamps();
    // }

    public function arbitres()
    {
        return $this->belongsToMany(User::class, 'league_users')
            ->withPivot('role_id')
            ->wherePivotIn('role_id', function ($query) {
                $query->select('id')
                    ->from('roles')
                    ->where('name', 'arbitre');
            });
    }

    protected static function booted()
    {
        static::creating(function ($league) {
            if (empty($league->invitation_code)) {
                $league->invitation_code = self::generateUniqueInvitationCode();
            }
        });
    }

    /**
     * Génère un code unique et s'assure qu'il n'existe pas déjà en BDD
     */
    private static function generateUniqueInvitationCode(): string
    {
        do {
            $code = 'LIG-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (self::where('invitation_code', $code)->exists());

        return $code;
    }
}
