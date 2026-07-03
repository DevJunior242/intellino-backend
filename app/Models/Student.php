<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Club;
use App\Models\User;
use App\Models\Licence;
use App\Models\ParentModel;
use Illuminate\Support\Str;
use App\Models\ExamenLeague;
use App\Models\ExamenResult;
use App\Models\StudentGrade;
use App\Models\ExamenCandidat;
use App\Models\StudentPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Student extends Model
{
    use HasUuids, SoftDeletes, Notifiable;
    protected $table = 'students';

    protected $fillable = [
        'id',
        'user_id',
        'is_adult',
        'fullname',
        'birthdate',
        'matricule',
        'sex',
        'photo',
        'status',
        'subscription_expires_at',
    ];

    const STATUS_ACTIV = 0;
    const STATUS_INACTIV = 1;
    protected $casts = [
        'subscription_expires_at' => 'datetime',
    ];
    public $incrementing = false;
    protected $keyType = 'string';

    public function parents()
    {
        return $this->belongsToMany(ParentModel::class, 'parent_students', 'student_id', 'parent_model_id')->withTimestamps();
    }

    public function clubs()
    {
        return $this->belongsToMany(Club::class, 'club_students', 'student_id', 'club_id')->withPivot('saison_id', 'is_active')
            ->withTimestamps();
    }

    public function candidates()
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
    // public function routeNotificationForMail()
    // {
    //     return $this->user?->email;
    // }
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

    public function getStatutLabelAttribute()
    {
        return match ($this->status) {
            self::STATUS_ACTIV => 'Actif',
            self::STATUS_INACTIV => 'Inactif',
            default => 'Inconnu',
        };
    }
    protected static function booted()
    {
        static::creating(function ($student) {
            if (empty($student->id)) {
                $student->id = (string) Str::uuid();
            }

            $currentYear = date('Y');


            $club = Club::with('country')->find(request()->attributes->get('organisateur_id'));

            // On récupère le code du pays (ex: 'BF', 'SN'). Si introuvable, on met 'XX'
            $countryCode = $club && $club->country ? $club->country->code : 'XX';

            // Compteur annuel pour ce pays spécifique
            $sequenceNumber = self::where('matricule', 'LIKE', "ATH-{$countryCode}-{$currentYear}-%")
                ->count() + 1;

            $paddedSequence = str_pad($sequenceNumber, 4, '0', STR_PAD_LEFT);

            $student->matricule = "ATH-" . strtoupper($countryCode) . "-" . $currentYear . "-" . $paddedSequence;
        });
    }
}
