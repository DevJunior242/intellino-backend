<?php

namespace App\Models;

use App\Models\Examen;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ExamenCandidat extends Model
{
    use HasUuids;
    protected $fillable = [
        'examen_id',
        'student_id',
        'status',
    ];
    public $incrementing = false;
    public $keyType = 'string';

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
