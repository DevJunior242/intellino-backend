<?php

namespace App\Models;

use App\Models\Student;
use App\Models\ExamenLeague;
use Illuminate\Database\Eloquent\Model;

class ExamenStudentLeague extends Model
{
    protected $table = 'examen_student_leagues';

    protected $fillable = [
        'examen_league_id',
        'student_id',
        'average',
        'passed',
    ];

    public function examenLeague()
    {
        return $this->belongsTo(ExamenLeague::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
