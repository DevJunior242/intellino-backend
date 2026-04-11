<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Student;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Licence extends Model
{
    use HasUuids;
    protected $table = 'licences';

    protected $fillable = [
        'id',
        'league_id',
        'club_id',
        'student_id',
        'saison',
        'numero',
        'type',
        'grade_au_moment',
        'montant',
        'date_emission',
        'date_expiration',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    protected static function booted()
    {
        static::saved(function ($licence) {
            static::clearStatsCache($licence->league_id);
        });

        // Se déclenche après une suppression
        static::deleted(function ($licence) {
            static::clearStatsCache($licence->league_id);
        });
    }

    /**
     * Nettoie toutes les clés de cache de catégories pour une ligue précise
     */
    protected static function clearStatsCache($leagueId)
    {
        // On récupère les IDs de toutes les catégories existantes
        $categoryIds = Category::pluck('id');

        foreach ($categoryIds as $id) {
            $key = "licencies_count_cat_{$id}_league_{$leagueId}";
            Cache::forget($key);
        }
    }
}
