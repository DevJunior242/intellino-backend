<?php

namespace App\Models;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class KataTeamMembre extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['kata_team_id', 'student_id', 'est_reserve'];

    protected $casts = [
        'est_reserve' => 'boolean',
    ];

    public function kataTeam()
    {
        return $this->belongsTo(KataTeam::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
