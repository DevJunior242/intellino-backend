<?php

namespace App\Models;

use App\Models\User;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ParentModel extends Model
{
    use HasUuids;
    protected $table = 'parent_models';

    protected $fillable = [
        'id',
        'user_id',
        'profession',
        'domicile',
        'relation',
    ];


    public $incrementing = false;
    protected $keyType = 'string';

    // Relationship with StudentModel through ParentStudent pivot table
    // public function students()
    // {
    //     return $this->belongsToMany(Student::class, 'parent_students')->withTimestamps();
    // }
    public function students()
    {
        return $this->belongsToMany(Student::class, 'parent_students', 'parent_model_id', 'student_id')->withTimestamps();
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
