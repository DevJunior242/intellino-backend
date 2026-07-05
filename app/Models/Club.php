<?php

namespace App\Models;

use App\Models\Like;
use App\Models\Role;
use App\Models\User;
use App\Models\Grade;
use App\Models\League;
use App\Models\Country;
use App\Models\Licence;
use App\Models\Student;
use App\Models\ClubUser;
use App\Models\Discipline;
use App\Models\Subscription;
use App\Models\AffiliationPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Club extends Model
{

    use HasUuids;
    protected $fillable = [
        'name',
        'discipline_id',
        'logo',
        'country_id',
        'league_id',
        'city',
        'region',
        'address',

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
        return $this->belongsToMany(Student::class, 'club_students', 'club_id', 'student_id')
            ->withPivot('saison_id', 'is_active')
            ->withTimestamps();
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

    public function affiliationPayments()
    {
        return $this->hasMany(AffiliationPayment::class);
    }

    public function licences()
    {
        return $this->hasMany(Licence::class);
    }
    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
