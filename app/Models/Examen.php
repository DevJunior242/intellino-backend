<?php

namespace App\Models;

use App\Models\Club;
use App\Models\User;
use App\Models\Grade;
use App\Models\ExamenResult;
use App\Models\ExamenCandidat;
use App\Models\ExamenEvaluation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Examen extends Model
{
    use HasUuids;

    protected $fillable = [
        'club_id',
        'current_grade_id',
        'next_grade_id',
        'start_date',
        'end_date',
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

    ];


    public $incrementing = false;
    public $keyType = 'string';

    const STATUS_SCHEDULED = 0;
    const STATUS_ONGOING = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_CANCELLED = 3;
    const STATUS_POSTPONED = 4;

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function currentGrade()
    {
        return $this->belongsTo(Grade::class, 'current_grade_id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class);
    }

    public function candidate()
    {
        return $this->hasMany(ExamenCandidat::class);
    }
    public function evaluations()
    {
        return $this->hasMany(ExamenEvaluation::class);
    }

    public function examenResult()
    {
        return $this->hasMany(ExamenResult::class);
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
