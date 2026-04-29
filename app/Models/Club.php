<?php

namespace App\Models;

use App\Models\Like;
use App\Models\Role;
use App\Models\User;
use App\Models\Grade;
use App\Models\League;
use App\Models\Licence;
use App\Models\Student;
use App\Models\ClubUser;
use App\Models\Discipline;
use App\Models\Affiliation;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Club extends Model
{

    use HasUuids;
    protected $fillable = [
        'name',
        'discipline_id',
        'logo',
        'country',
        'city',
        'address',
        'phone',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }


    public function users()
    {
        return $this->belongsToMany(User::class, 'club_users')
            ->using(ClubUser::class)
            ->withPivot('role_id')
            ->withTimestamps();
    }
    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'club_users')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function discipline()
    {
        return $this->belongsTo(Discipline::class);
    }

    //pivot 
    public function pivot()
    {
        return $this->belongsToMany(Role::class, 'club_users')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function league()
    {
        return $this->belongsTo(League::class);
    }

    public function affiliations()
    {
        return  $this->hasMany(Affiliation::class);
    }

    public function licences()
    {
        return $this->hasMany(Licence::class);
    }
}
