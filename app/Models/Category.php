<?php

namespace App\Models;

use App\Models\Saison;
use App\Models\Licence;
use App\Models\Disciplineleague;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Category extends Model
{
    use HasUuids;

    protected $fillable = ['nom', 'sexe', 'age_min', 'age_max', 'saison_id'];
    protected $keyType = 'string';
    public $incrementing = false;
    public function getLicenciesCount(string $activeId, $saisonId): int
    {
        $dateAujourdhui = \Carbon\Carbon::now();
        $dateNaissanceMin = $dateAujourdhui->copy()->subYears($this->age_max)->format('Y-m-d');
        $dateNaissanceMax = $dateAujourdhui->copy()->subYears($this->age_min)->format('Y-m-d');


        return Licence::where('league_id', $activeId)
            ->where('saison_id', $saisonId)
            ->where('status', Licence::STATUS_ACTIVE)
            ->whereHas('student', function ($q) use ($dateNaissanceMin, $dateNaissanceMax) {
                $q->whereBetween('birthdate', [$dateNaissanceMin, $dateNaissanceMax]);

                if ($this->sexe !== 'Mixte') {
                    $q->where('sex', $this->sexe);
                }
            })
            ->count();
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
}
