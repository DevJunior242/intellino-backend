<?php

namespace App\Models;

use App\Models\Club;
use App\Models\League;
use App\Models\Student;
use App\Models\ExamenStudentLeague;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ExamenLeague extends Model
{
    use HasUuids;
    protected $table = 'examen_leagues';

    protected $fillable = [
        'league_id',
        'title',
        'grade',
        'description',
        'start_date',
        'end_date',
    ];
    protected $keyType = 'string';
    public $incrementing = false;
    public function league()
    {
        return $this->belongsTo(League::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'examen_student_leagues', 'examen_league_id', 'student_id')
            ->withPivot('average', 'passed')
            ->withTimestamps();
    }
}
