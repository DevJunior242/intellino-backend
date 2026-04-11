<?php

namespace App\Models;

use App\Models\User;
use App\Models\Examen;
use App\Models\Student;
use App\Models\GradeEnchainement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ExamenEvaluation extends Model
{
    use HasUuids;
    protected $table = 'examen_evaluations';

    protected $fillable = ['examen_id', 'student_id', 'enchainement_id', 'score', 'comment', 'evaluated_by'];
    protected $casts = [
        'score' => 'float',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function enchainement()
    {
        return $this->belongsTo(GradeEnchainement::class);
    }
    public function evaluatedBy()
    {
        return $this->belongsTo(User::class);
    }
}
