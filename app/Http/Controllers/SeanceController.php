<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Competition;
use App\Models\Inscription;
use App\Models\OrdrePassage;
use Illuminate\Http\Request;
use App\Events\TatamiUpdated;
use App\Models\ConfigNotation;
use App\Models\JugeCompetition;
use App\Models\RotationArbitre;
use Illuminate\Validation\Rule;
use App\Services\BracketService;
use Illuminate\Support\Benchmark;
use App\Models\ArbitreCompetition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Services\KataNotationService;
use App\Services\RotationArbitreService;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SendArbitreAccessCodesNotif;

class SeanceController extends Controller
{
    public function __construct(
        private RotationArbitreService $rotationService,
        private KataNotationService $kataNotationService
    ) {}

    // Appelé quand admin valide la config
    // Lance la rotation initiale
    public function valider(ConfigNotation $config)
    {
        $start = microtime(true);

        try {
            $problemes = $config->estPrete();
            if (!empty($problemes)) {
                return response()->json([
                    'success'   => false,
                    'problemes' => $problemes,
                ], 422);
            }

            $nbCombattants = OrdrePassage::where('config_notation_id', $config->id)->count();
            $minimumsFormat = ['poules' => 3, 'poules_eliminatoire' => 3, 'eliminatoire' => 2];

            // Si l'appel envoie un `kumite_format_id`, valider sa compatibilité
            // avec l'effectif avant de le persister (permet au client de remplir le choix).
            $request = request();
            if ($request->filled('kumite_format_id')) {
                $fmt = \App\Models\KumiteFormat::find($request->input('kumite_format_id'));
                if ($fmt) {
                    $minRequis = $minimumsFormat[$fmt->code] ?? 2;
                    if ($nbCombattants < $minRequis) {
                        return response()->json([
                            'success' => false,
                            'error'   => 'format_incompatible',
                            'message' => "Le format « {$fmt->libelle} » nécessite au moins {$minRequis} combattants (actuellement {$nbCombattants}).",
                        ], 422);
                    }

                    $config->updateQuietly(['kumite_format_id' => $fmt->id]);
                    $config->load('kumiteFormat');
                }
            }

            // Si kumite et aucun format choisi, demander au client de sélectionner
            // un format explicitement plutôt que de le choisir automatiquement.
            if ($config->estKumite() && !$config->kumite_format_id) {
                $formats = \App\Models\KumiteFormat::all()
                    ->filter(fn($f) => $nbCombattants >= ($minimumsFormat[$f->code] ?? 2))
                    ->values();

                return response()->json([
                    'success' => false,
                    'error'   => 'missing_kumite_format',
                    'message' => 'Veuillez choisir un format kumite avant de valider la séance',
                    'formats' => $formats,
                ], 422);
            }

            DB::transaction(function () use ($config) {
                if ($config->estKumite()) {
                    app(BracketService::class)->generer($config);
                }

                if (!$config->relationLoaded('competition')) {
                    $config->load('competition');
                }

                $evenementId = $config?->competition?->evenement_id;
                $arbitresSansCode = ArbitreCompetition::where('evenement_id', $evenementId)
                    ->whereNull('code_acces')
                    ->get();

                foreach ($arbitresSansCode as $arbitre) {
                    $arbitre->updateQuietly([
                        'code_acces' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT)
                    ]);
                }

                if ($config->estModeTablettes()) {
                    $arbitresSansCode->load('user');
                    Notification::send(
                        $arbitresSansCode,
                        new SendArbitreAccessCodesNotif()
                    );
                }
                $config->updateQuietly([
                    'configuration_validee' => true,
                    'validee_a'             => now(),
                ]);
            });

            broadcast(new TatamiUpdated($config->id));

            return response()->json([
                'success' => true,
                'message' => 'Séance ouverte',
                'debug'   => [
                    'temps_execution_ms' => round((microtime(true) - $start) * 1000, 2),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    public function lancerSeance(ConfigNotation $config)
    {
        // Mode tablettes — vérifier que tous les arbitres actifs sont connectés
        if ($config->estModeTablettes()) {

            $problemes = $config->estPretePourSaisie();
            if (!empty($problemes)) {
                return response()->json([
                    'success'   => false,
                    'problemes' => $problemes,
                ], 422);
            }
        }



        //exist en cours athlete
        $existAthlete = OrdrePassage::where('config_notation_id', $config->id)
            ->where('status', OrdrePassage::STATUS_STARTED)
            ->first();

        // Premier athlète en cours
        if (!$existAthlete) {
            $premier = OrdrePassage::where('config_notation_id', $config->id)
                ->where('status', OrdrePassage::STATUS_NOT_STARTED)
                ->orderBy('ordre')
                ->first();

            if (!$premier) {
                return response()->json([
                    'success' => false,
                    'problemes' => ['Aucun athlète en attente'],
                ], 422);
            }
            $premier->update(['status' => OrdrePassage::STATUS_STARTED]);
            //passer de config a inscription pourr recuperer athlete
            $premier->load(['inscription.athlete']);
            $monAthle = $premier->inscription->athlete->fullname ?? "Aucun athlète";
            broadcast(new TatamiUpdated($config->id));
            return response()->json([
                'success' => true,
                'message' => "Séance lancée pour {$monAthle}",
                'enCours' => $existAthlete ? $existAthlete->load('inscription.athlete') : null,
            ], 201);
        }
        broadcast(new TatamiUpdated($config->id));
        return response()->json([
            'success' => true,
            'message' => 'La séance a déjà été lancée pour cet athlète',
            'enCours' => $existAthlete->load('inscription.athlete'),
        ], 200);
    }
    // Appelé quand athlète suivant passe
    public function athleteSuivant(ConfigNotation $config)
    {
        // Marquer l'athlète en cours comme terminé
        $passageActuel = OrdrePassage::where('config_notation_id', $config->id)
            ->where('status', OrdrePassage::STATUS_STARTED)
            ->with('notes')
            ->first();

        if ($passageActuel) {
            $valeursNotes = $passageActuel->notes->pluck('valeur')->map(fn($v) => (float)$v)->toArray();

            $scoreFinal = $this->kataNotationService->calculerScore($valeursNotes, $config->getNbJuges());
            $passageActuel->update(['status' => OrdrePassage::STATUS_FINISHED, 'score_final' => $scoreFinal]);
        }
        // Faire tourner les arbitres
        //$this->rotationService->tourner($config);

        // Passer l'athlète suivant en cours
        $suivant = OrdrePassage::where('config_notation_id', $config->id)
            ->where('status', OrdrePassage::STATUS_NOT_STARTED)
            ->orderBy('ordre')
            ->first();

        if ($suivant) {
            $suivant->update(['status' => OrdrePassage::STATUS_STARTED]);
        }
        broadcast(new TatamiUpdated($config->id));

        return response()->json([
            'success' => true,
            'suivant' => $suivant,
            'etat'    => $this->rotationService->etat($config),
        ]);
    }


    // État actuel pour l'affichage admin / tablettes
    public function etat(ConfigNotation $config)
    {
        return response()->json([
            'success' => true,
            'etat'    => $this->rotationService->etat($config),
            'enCours' => Inscription::where('competition_id', $config->competition_id)
                ->where('status', 'en_cours')
                ->with('athlete')
                ->first(),
        ]);
    }


    public function connecterTablette(Request $request, ConfigNotation $config)
    {
        $validated = $request->validate([
            'code_acces' => 'required|digits:6',
        ]);

        // Chargement de la compétition en amont si non fait par le Router Model Binding
        if (!$config->relationLoaded('competition')) {
            $config->load('competition');
        }
        // Récupère l'arbitre avec sa SEULE rotation active sur ce plateau
        $arbitre = ArbitreCompetition::where('evenement_id', $config->competition->evenement_id)
            ->where('user_id', auth()->id())
            ->where('code_acces', $validated['code_acces'])
            ->withWhereHas('rotations', function ($query) use ($config) {
                $query->where('config_notation_id', $config->id)
                    ->where('actif', true); // Filtre pour avoir la rotation en cours
            })
            ->with('user:id,fullname')
            ->first();

        if (!$arbitre) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Vérifiez votre code ou votre affectation active.',
            ], 422);
        }

        // Extraction de la rotation active filtrée par le withWhereHas
        $rotation = $arbitre->rotations->first();

        // Sécurité si le poste est nul malgré le statut actif
        if (is_null($rotation->poste)) {
            if (!$rotation->est_superviseur) {
                return response()->json([
                    'success' => false,
                    'message' => 'En attente d\'affectation à un poste actif...'
                ], 422);
            }
        }

        // Mise à jour rapide de l'état de connexion
        $arbitre->timestamps = false;
        $arbitre->updateQuietly(['connecte' => true]);


        return response()->json([
            'success' => true,
            'message' => "Connecté au poste {$rotation->poste}",
            'poste'   => $rotation->poste,
            'nom'     => $arbitre->user->fullname,
            'superviseur' => $rotation->est_superviseur,

        ]);
    }


    public function estPret(ConfigNotation $config)
    {
        $problemes = $config->estPretePourSaisie();

        return response()->json([
            'pret'      => empty($problemes),
            'problemes' => $problemes,
        ]);
    }

    public function enCours(ConfigNotation $config)
    {

        return OrdrePassage::query()
            ->where('config_notation_id', $config->id)
            ->where('status', OrdrePassage::STATUS_STARTED)
            ->with([
                'inscription',
                'inscription.athlete:id,fullname',
                'inscription.competition',
                'inscription.competition.category:id,nom,sexe',
                'inscription.organisateur:id,name',
                'notes'
            ])
            ->first();
    }
    //recuperer le prochain athlète
    public function nextAthlete(ConfigNotation $config)
    {
        return OrdrePassage::query()
            ->where('config_notation_id', $config->id)
            ->where('status', OrdrePassage::STATUS_NOT_STARTED)
            ->orderBy('ordre')
            ->with([
                'inscription',
                'inscription.athlete:id,fullname',
                'inscription.competition',
                'inscription.competition.category:id,nom,sexe',
                'inscription.organisateur:id,name',
            ])
            ->first();
    }

    // Retourner les arbitres de la rotation pour ce tatami
    public function arbitresRotation(ConfigNotation $config)
    {

        $arbitres = RotationArbitre::where('config_notation_id', $config->id)
            ->with('arbitreCompetition.user:id,fullname')
            ->orderBy('ordre')
            ->get()
            ->map(fn($r) => [
                'id'                     => $r->id,
                'arbitre_competition_id' => $r->arbitre_competition_id,
                'nom'                    => $r->arbitreCompetition->user->fullname,
                'poste'                  => $r->poste,
                'actif'                  => $r->actif,
                'est_superviseur'        => $r->est_superviseur,
                'nb_passages'            => $r->nb_passages,
            ]);

        $superviseur = $arbitres->firstWhere('est_superviseur', true);
        return response()->json([
            'success'     => true,
            'arbitres'    => $arbitres,
            'superviseur' => $superviseur,
        ]);
    }

    // Désigner superviseur
    public function designerSuperviseur(Request $request, ConfigNotation $config)
    {
        $validated = $request->validate([
            'arbitre_competition_id' => [
                'required',
                Rule::exists('rotation_arbitres', 'arbitre_competition_id')
                    ->where('competition_id', $config->competition_id),
            ],
        ]);

        // Retirer ancien superviseur
        RotationArbitre::where('competition_id', $config->competition_id)
            ->update(['est_superviseur' => false]);

        // Désigner nouveau
        RotationArbitre::where('competition_id', $config->competition_id)
            ->where('arbitre_competition_id', $validated['arbitre_competition_id'])
            ->update(['est_superviseur' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Superviseur désigné avec succès',
        ]);
    }




    // Controller
    public function vuePublique(ConfigNotation $config)
    {
        $start = microtime(true);
        $enCours = OrdrePassage::where('config_notation_id', $config->id)
            ->where('status', OrdrePassage::STATUS_STARTED)
            ->with([
                'inscription.athlete:id,fullname',
                'inscription.organisateur:id,name',
                'inscription.competition.category:id,nom,sexe',
                'inscription.competition.evenement:id,nom,lieu',
                'inscription',
                'notes'
            ])
            ->first();

        $notes = [];
        $score = null;

        if ($enCours) {
            $notesData = Note::where('ordre_passage_id', $enCours->id)
                ->with([
                    'rotationArbitre.arbitreCompetition.user:id,fullname',
                    'ordrePassage.inscription.athlete:id,fullname'
                ])
                ->get()
                ->map(fn($n) => [
                    'valeur' => (float) $n->valeur,
                    'poste'   => $n->rotationArbitre?->poste ?? "Inconnu",
                    'arbitre' => $n->rotationArbitre?->arbitreCompetition?->user?->fullname ?? "Inconnu",
                ])
                ->sortBy('poste')
                ->values();

            $notes = $notesData;

            if ($notesData->count() === $config->getNbJuges()) {
                $valeurs  = $notesData->pluck('valeur')->toArray();
                $score    = $this->kataNotationService->calculerScore($valeurs, $config->getNbJuges());
            }
        }

        // Classement provisoire (Art. 5.11 — à score retenu égal, départage
        // sur la somme brute des notes puis la note individuelle la plus haute)
        $classement = OrdrePassage::where('config_notation_id', $config->id)
            ->where('status', OrdrePassage::STATUS_FINISHED)
            ->with([
                'inscription.athlete:id,fullname',
                'inscription.organisateur:id,name',
                'notes'
            ])
            ->get()
            ->map(function ($passage) use ($config) {
                $valeurs = $passage->notes
                    ->pluck('valeur')
                    ->map(fn($v) => (float) $v)
                    ->toArray();

                return [
                    'athlete'      => $passage->inscription?->athlete?->fullname ?? '—',
                    'organisateur' => $passage->inscription?->organisateur?->name ?? '—',
                    'score'        => $this->kataNotationService->calculerScore($valeurs, $config->getNbJuges()),
                    'valeurs'      => $valeurs,
                ];
            })
            ->filter(fn($item) => $item['score'] !== null)
            ->sort(function ($a, $b) {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }
                return $this->kataNotationService->departagerEgalite($a['valeurs'], $b['valeurs']);
            })
            ->values()
            ->map(fn($item) => collect($item)->except('valeurs')->all());


        return response()->json([
            'success'    => true,
            'config'     => [
                'id'          => $config->id,
                'competition' => $config->competition->nom ?? 'Compétition',
                'nb_juges'    => $config->getNbJuges(),
            ],
            'enCours'    => $enCours,
            'notes'      => $notes,
            'score'      => $score ?? null,
            'classement' => $classement,
        ]);
    }
}
