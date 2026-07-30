<?php

namespace App\Models;

use App\Models\Club;
use App\Models\User;
use App\Models\Grade;
use App\Models\ExamenResult;
use App\Models\ExamenPayment;
use App\Models\ExamenCandidat;
use App\Models\ExamenEvaluation;
use App\Models\GradeEnchainement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Examen extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisateur_id',
        'organisateur_type',
        'saison_id',
        'current_grade_id',
        'next_grade_id',
        'start_date',
        'end_date',
        'old_start_date',
        'old_end_date',
        'status',
        'created_by',
        'cancel_reason',
        'cancelled_at',
        'actual_start_time',
        'actual_end_time',
        'start_time',
        'end_time',
        'replacement_start_time',
        'replacement_end_time',
        'price',

    ];


    public $incrementing = false;
    public $keyType = 'string';

    const STATUS_SCHEDULED = 0;
    const STATUS_ONGOING = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_CANCELLED = 3;
    const STATUS_POSTPONED = 4;

    public function organisateur()
    {
        return $this->morphTo();
    }

    public function currentGrade()
    {
        return $this->belongsTo(Grade::class, 'current_grade_id');
    }
    public function nextGrade()
    {
        return $this->belongsTo(Grade::class, 'next_grade_id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class);
    }

    public function candidates()
    {
        return $this->hasMany(ExamenCandidat::class);
    }
    public function payments()
    {
        return $this->hasMany(ExamenPayment::class);
    }
    public function evaluations()
    {
        return $this->hasMany(ExamenEvaluation::class);
    }

    public function examenResult()
    {
        return $this->hasMany(ExamenResult::class);
    }
    public function enchainements()
    {
        return $this->hasMany(GradeEnchainement::class);
    }

    public function getStatutLabelAttribute()
    {
        return match ($this->status) {
            self::STATUS_SCHEDULED => 'Planifié',
            self::STATUS_ONGOING   => 'En cours',
            self::STATUS_COMPLETED => 'Terminé',
            self::STATUS_CANCELLED => 'Annulé',
            self::STATUS_POSTPONED => 'Postponé',
            default                => 'Inconnu',
        };
    }
}
