<?php

namespace App\Models;

use App\Models\User;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StudentGrade extends Model
{
    use HasUuids;
    protected $table = 'student_grades';

    protected $fillable = [
        'student_id',
        'current_grade_id',
        'awarded_at',
        'instructor_id',
        'club_id',
        'is_current'
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function currentGrade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function instructor()
    {
        return $this->belongsTo(User::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'current_grade_id');
    }
}
