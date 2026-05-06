<?php

namespace App\Models;

use App\Models\Plateau;
use App\Models\JugeOption;
use App\Models\ModeSaisie;
use App\Models\Competition;
use App\Models\KumiteFormat;
use App\Models\KumuteFormat;
use App\Models\JugeCompetition;
use App\Models\RotationArbitre;
use App\Models\ArbitreCompetition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ConfigNotation extends Model
{
    use HasUuids;
    protected $fillable = [
        'plateau_id',
        'competition_id',
        'mode_saisie_id',
        'nb_juges_option_id',
        'configuration_validee',
        'configure_par',
        'validee_a',
        'kumite_format_id',
        'duration',
    ];

    protected $casts = [
        'configuration_validee' => 'boolean',
        'validee_a'             => 'datetime',
    ];

    public function organisateur()
    {
        return $this->morphTo();
    }
    // Relations
    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function modeSaisie()
    {
        return $this->belongsTo(ModeSaisie::class, 'mode_saisie_id');
    }

    public function nbJugesOption()
    {
        return $this->belongsTo(JugeOption::class, 'nb_juges_option_id');
    }

    public function juges()
    {
        return $this->hasMany(JugeCompetition::class)
            ->orderBy('numero_juge');
    }
    public function kumiteFormat()
    {
        return $this->belongsTo(KumiteFormat::class, 'kumite_format_id');
    }
    public function plateau()
    {
        return $this->belongsTo(Plateau::class, 'plateau_id');
    }

    // Helpers — plus de comparaison avec strings hardcodées
    public function estModeTablettes(): bool
    {
        return $this->modeSaisie->code === 'tablettes';
    }

    public function estModeCentralise(): bool
    {
        return $this->modeSaisie->code === 'centralise';
    }

    public function getNbJuges(): int
    {
        return (int) ($this->nbJugesOption?->valeur ?? 5);
    }
    //esKumite
    public function estKumite(): bool
    {
        $discipline = $this->competition->discipline->nom ?? '';
        return trim(strtolower($discipline)) === 'kumite';
    }

    public function estKata(): bool
    {
        return optional($this->competition?->discipline)->nom === 'kata';
    }
    // Niveau 1 — config complète (admin peut valider)
    public function estPrete(): array
    {
        $problemes = [];

        // Récupère 5 ou 7 (Le panel qui donne les points/notes)
        $nbJugesPanel = $this->getNbJuges();

        // Règle universelle de ton SaaS : Panel + 1 Superviseur (Kansa)
        $nbTotalBesoin = $nbJugesPanel + 1;
        // Compter les arbitres réellement assignés et actifs sur cette config
        $arbitresAssignes = RotationArbitre::where('config_notation_id', $this->id)
            ->where('actif', true)
            ->whereNotNull('arbitre_competition_id')
            ->count();

        // Vérifier si certains de ces arbitres sont déjà occupés sur un autre plateau
        $doublons = RotationArbitre::where('actif', true)
            ->whereNotNull('arbitre_competition_id')
            ->where('config_notation_id', '!=', $this->id)
            ->whereIn('arbitre_competition_id', function ($query) {
                $query->select('arbitre_competition_id')
                    ->from('rotation_arbitres')
                    ->where('config_notation_id', $this->id);
            })
            ->count();

        $disponiblesReels = $arbitresAssignes - $doublons;

        if ($disponiblesReels < $nbTotalBesoin) {
            $manque = $nbTotalBesoin - $disponiblesReels;
            $mode = $this->estKumite() ? "Kumite" : "Kata";

            $problemes[] = "Tatami {$mode} incomplet.";
            $problemes[] = "Besoin : {$nbTotalBesoin} officiels (Panel de {$nbJugesPanel} + 1 Superviseur).";
            $problemes[] = "Actuellement assignés : {$arbitresAssignes}. Il en manque {$manque}.";
        }

        return $problemes;
    }
    // Niveau 2 — séance peut démarrer (tous connectés)
    public function estPretePourSaisie(): array
    {
        $problemes = [];

        if (!$this->configuration_validee) {
            $problemes[] = "Configuration pas encore validée";
        }

        if ($this->estModeTablettes()) {
            $nonConnectes = ArbitreCompetition::where('evenement_id', $this->evenement_id)
                ->whereHas('rotation', fn($q) => $q->where('actif', true))
                ->where('connecte', false)
                ->count();
            if ($nonConnectes > 0) {
                $problemes[] = "{$nonConnectes} juge(s) pas encore connecté(s)";
            }

            $aSuperviseur = RotationArbitre::where('config_notation_id', $this->id)
                ->where('est_superviseur', true)
                ->exists();
            if (!$aSuperviseur) {
                $problemes[] = "Aucun superviseur désigné pour ce tatami";
            }
        }

        return $problemes;
    }
}
