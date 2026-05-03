<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Grade;
use App\Models\Instructor;
use App\Models\SessionModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Course extends Model
{
    use HasUuids;
    protected $table = 'courses';
    protected $fillable = [
        'club_id',
        'saison_id',
        'name',
        'level',
        'instructor_id',
        'current_grade_id',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }

    public function sessions()
    {
        return $this->hasMany(SessionModel::class);
    }
    public function grade()
    {
        return $this->belongsTo(Grade::class, 'current_grade_id');
    }
}
