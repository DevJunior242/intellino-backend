<?php

namespace App\Models;

use App\Models\Saison;
use App\Models\Licence;
use App\Models\Disciplineleague;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Category extends Model
{
    use HasUuids;
    protected $appends = ['licencies_count'];

    protected $fillable = ['nom', 'sexe', 'age_min', 'age_max', 'saison_id', 'league_id'];
    protected $keyType = 'string';
    public $incrementing = false;

    public function getLicenciesCountAttribute()
    {
        $user = auth()->user();
        $leagueId = $user->current_league_id;


        $cacheKey = "licencies_count_cat_{$this->id}_league_{$leagueId}";

        // On garde en cache 60 minutes, sauf si Licence.php le vide avant
        return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($leagueId) {
            // 1. On calcule les dates de naissance limites en PHP
            // Pour avoir entre 18 et 34 ans aujourd'hui :
            $dateAujourdhui = \Carbon\Carbon::now();

            // Né au plus tard il y a 18 ans (ex: 2008)
            $neApres = $dateAujourdhui->copy()->subYears($this->age_min)->format('Y-m-d');

            // Né au plus tôt il y a 34 ans (ex: 1992)
            $neAvant = $dateAujourdhui->copy()->subYears($this->age_max + 1)->addDay()->format('Y-m-d');

            return Licence::where('league_id', $leagueId)
                ->where('statut', 'active')
                ->whereHas('student', function ($query) use ($neAvant, $neApres) {
                    $query->whereBetween('birthdate', [$neAvant, $neApres]);
                })
                ->count();
        });
    }


    public function disciplines()
    {
        return $this->belongsToMany(Disciplineleague::class, 'category_disciplineleagues')
            ->withTimestamps();
    }

    public function saison()
    {
        return $this->belongsTo(Saison::class);
    }

    public function licencies()
    {
        return $this->hasMany(Licence::class);
    }

    // Compte automatique des licenciés
    // public function getLicenciesCountAttribute()
    // {
    //     return $this->licencies()->count();
    // }

}
