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
        'saison_id',
        'numero',
        'type',
        'grade_au_moment',
        'montant',
        'date_emission',
        'date_expiration',
        'status',
    ];
    const STATUS_ACTIVE = 0;
    const STATUS_EXPIRED = 1;
    protected $keyType = 'string';
    public $incrementing = false;


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

    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Actif',
            self::STATUS_EXPIRED => 'Expiré',
            default => 'Inconnu',
        };
    }
}
