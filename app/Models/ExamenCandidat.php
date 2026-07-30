<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Examen;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ExamenCandidat extends Model
{
    use HasUuids, Notifiable;
    protected $fillable = [
        'examen_id',
        'student_id',
        'club_id',
        'status',
    ];
    public $incrementing = false;
    public $keyType = 'string';
    const STATUS_REGISTERED = 0;
    const STATUS_ABSENT = 1;
    const STATUS_EVALUATED = 2;


    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
