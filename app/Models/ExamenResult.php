<?php

namespace App\Models;

use App\Models\Examen;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ExamenResult extends Model
{
    use HasUuids;
    protected $table = 'examen_results';
    protected $fillable = ['examen_id', 'student_id', 'total_score', 'decision', 'decided_by', 'new_grade_id'];
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
}
