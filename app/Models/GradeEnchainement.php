<?php

namespace App\Models;

use App\Models\Grade;
use App\Models\ExamenEvaluation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class GradeEnchainement extends Model
{
    use HasUuids;

    protected $fillable = [
        'examen_id',
        'club_id',
        'current_grade_id',
        'name',
        'description',
        'order',
        'diviseur',
    ];
//   protected   $guarded=[];

    public $incrementing = false;
    public $keyType = 'string';

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }
    
    public function evaluations()
    {
        return $this->hasMany(ExamenEvaluation::class, 'enchainement_id');
    }
}
