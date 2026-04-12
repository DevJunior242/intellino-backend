<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Course;
use App\Models\Examen;
use App\Models\StudentGrade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Grade extends Model
{
    use HasUuids;
    protected $table = 'grades';

    protected $fillable = [
        'name',
        'description',
    ];
    public $incrementing = false;
    protected $keyType = 'string';

    public function students()
    {
        return $this->hasMany(StudentGrade::class);
    }


    public function examen()
    {
        return $this->hasMany(Examen::class);
    }
    public function course()
    {
        return $this->hasMany(Course::class);
    }
}
