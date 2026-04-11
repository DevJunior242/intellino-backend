<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Kata;
use App\Models\Note;
use App\Models\Student;
use App\Models\Category;
use App\Models\Competition;
use App\Models\ConfigNotation;
use App\Models\Disciplineleague;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Inscription extends Model
{
    use HasUuids;
    protected $table = 'inscriptions';
    const STATUS_ATTENTE = 0;
    const STATUS_VALIDE = 1;
    const STATUS_ECHOUE = 2;


    protected $fillable = [
        'competition_id',
        'athlete_id',
        'kata_id',
        // 'poids_declare',
        'poids_officiel',
        'statut_pesee',
        'ordre_passage',
        'statut_passage',
        'club_id',
    ];

    protected $keyType = 'string';
    public $incrementing = false;
    public function athlete()
    {
        return $this->belongsTo(Student::class, 'athlete_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class, 'competition_id');
    }

    public function disciplineleague()
    {
        return $this->belongsTo(Disciplineleague::class, 'disciplineleague_id');
    }
    //club
    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
    // Une fonction magique (Accessor) pour l'affichage
    public function getStatutTexteAttribute()
    {
        return match ($this->statut_pesee) {
            self::STATUS_ATTENTE => 'En attente',
            self::STATUS_VALIDE  => 'Validé',
            self::STATUS_ECHOUE  => 'Échoué',
            default => 'Inconnu',
        };
    }

    public function notes()
    {
        return $this->hasMany(Note::class, 'inscription_id');
    }
    public function kata()
    {
        return $this->belongsTo(Kata::class, 'kata_id');
    }

    public function configNotation()
    {
        return $this->belongsTo(ConfigNotation::class, 'config_notation_id');
    }
}
