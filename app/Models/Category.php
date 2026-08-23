<?php

namespace App\Models;

use App\Models\Saison;
use App\Models\Licence;
use App\Models\SubDiscipline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Category extends Model
{
    use HasUuids;

    protected $fillable = ['nom', 'sexe', 'age_min', 'age_max', 'poids_min', 'poids_max', 'saison_id', 'organisateur_id', 'organisateur_type'];
    protected $keyType = 'string';
    public $incrementing = false;
    /**
     * Nombre de licenciés validés de cette catégorie, pour une fédération et
     * une saison données. $leagueId permet de restreindre le comptage aux
     * clubs d'une ligue précise (vue Ligue), sinon on compte toute la fédération.
     */
    public function getLicenciesCount(string $federationId, $saisonId, ?string $leagueId = null): int
    {
        $dateAujourdhui = \Carbon\Carbon::now();
        $dateNaissanceMin = $dateAujourdhui->copy()->subYears($this->age_max)->format('Y-m-d');
        $dateNaissanceMax = $dateAujourdhui->copy()->subYears($this->age_min)->format('Y-m-d');

        return Licence::where('federation_id', $federationId)
            ->where('saison_id', $saisonId)
            ->where('status', Licence::STATUS_PAYE)
            ->when($leagueId, function ($query) use ($leagueId) {
                $query->whereHas('club', fn($q) => $q->where('league_id', $leagueId));
            })
            ->whereHas('student', function ($q) use ($dateNaissanceMin, $dateNaissanceMax) {
                $q->whereBetween('birthdate', [$dateNaissanceMin, $dateNaissanceMax]);

                if ($this->sexe !== 'Mixte') {
                    $q->where('sex', $this->sexe);
                }
            })
            ->count();
    }


    // Vérifie qu'un poids donné respecte les bornes de la catégorie (Kumite).
    // Une catégorie sans bornes définies (ex: Kata) laisse toujours passer.
    public function isPoidsValide(?float $poids): bool
    {
        if (is_null($poids) || (is_null($this->poids_min) && is_null($this->poids_max))) {
            return true;
        }

        if (!is_null($this->poids_min) && $poids < $this->poids_min) {
            return false;
        }

        if (!is_null($this->poids_max) && $poids > $this->poids_max) {
            return false;
        }

        return true;
    }

    public function getPoidsLabelAttribute(): ?string
    {
        if (is_null($this->poids_min) && is_null($this->poids_max)) {
            return null;
        }

        if (is_null($this->poids_min)) {
            return "-{$this->poids_max}kg";
        }

        if (is_null($this->poids_max)) {
            return "+{$this->poids_min}kg";
        }

        return "{$this->poids_min}-{$this->poids_max}kg";
    }

    public function disciplines()
    {
        return $this->belongsToMany(SubDiscipline::class, 'category_subdisciplines')
            ->withTimestamps();
    }

    public function saison()
    {
        return $this->belongsTo(Saison::class);
    }

    public function organisateur()
    {
        return $this->morphTo();
    }

    public function licencies()
    {
        return $this->hasMany(Licence::class);
    }
}
