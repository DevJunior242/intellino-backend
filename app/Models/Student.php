<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Club;
use App\Models\User;
use App\Models\Licence;
use App\Models\ParentModel;
use App\Models\ExamenLeague;
use App\Models\ExamenResult;
use App\Models\StudentGrade;
use App\Models\StudentParent;
use App\Models\ExamenCandidat;
use App\Models\StudentPayment;
use App\Models\ExamenStudentLeague;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Student extends Model
{
    use HasUuids, SoftDeletes;
    protected $table = 'students';

    protected $fillable = [
        'id',
        'club_id',
        'user_id',
        'is_adult',
        'fullname',
        'birthdate',
        'sex',
        'photo',
        'status',
        'subscription_expires_at',
    ];

    protected $attributes = [
        'status' => 'inactif',
    ];
    protected $casts = [
        'subscription_expires_at' => 'datetime',
    ];
    public $incrementing = false;
    protected $keyType = 'string';

    public function parents()
    {
        return $this->belongsToMany(ParentModel::class, 'parent_students', 'student_id', 'parent_model_id')->withTimestamps();
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function candidate()
    {
        return $this->hasMany(ExamenCandidat::class);
    }

    public function grades()
    {
        return $this->hasMany(StudentGrade::class);
    }
    public function currentGrade()
    {
        return $this->hasOne(StudentGrade::class)
            ->latestOfMany('awarded_at');
    }
    public function examenResult()
    {
        return $this->hasMany(ExamenResult::class);
    }

    public function parent()
    {
        return $this->belongsTo(ParentModel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAgeAttribute()
    {
        return Carbon::parse($this->birthdate)->age;
    }
    public function isAdult()
    {
        return $this->age >= 18;
    }

    public function getDisplayNameAttribute()
    {
        if ($this->user_id && $this->user) {
            return $this->user->fullname;
        }

        return $this->fullname;
    }
    public function payments()
    {
        return $this->hasMany(StudentPayment::class);
    }

    public function examenLeagues()
    {
        return $this->belongsToMany(ExamenLeague::class, 'examen_student_leagues', 'student_id', 'examen_league_id')
            ->withPivot('average', 'passed')
            ->withTimestamps();
    }

    public function licences()
    {
        return $this->hasMany(Licence::class);
    }

    public function isSubscriptionActive(): bool
    {
        if (!$this->subscription_expires_at) {
            return false;
        }

        return $this->subscription_expires_at->isFuture();
    }
}
