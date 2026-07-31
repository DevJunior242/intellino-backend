<?php

namespace App\Models;

use App\Models\Student;
use App\Models\Inscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class KataTeam extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['inscription_id', 'nom'];

    public function inscription()
    {
        return $this->belongsTo(Inscription::class);
    }

    public function membres()
    {
        return $this->hasMany(KataTeamMembre::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'kata_team_membres')
            ->withPivot('est_reserve')
            ->withTimestamps();
    }
}
