<?php

namespace App\Http\Controllers;

use App\Models\Plateau;
use App\Models\JugeOption;
use App\Models\ModeSaisie;
use App\Models\Competition;
use App\Models\Inscription;
use Illuminate\Http\Request;
use App\Models\ConfigNotation;
use App\Models\JugeCompetition;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ConfigNotationController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $activeOrgId = $user->current_league_id ?? $user->current_federation_id;
        $activeOrgType = $user->current_league_id ? 'Ligue' : 'Federation';

        if (!$activeOrgId) {
            return response()->json(['message' => 'Aucune organisation active'], 403);
        }


        $configs = ConfigNotation::whereHas('competition.evenement', function ($q) use ($activeOrgId, $activeOrgType) {
            $q->where('organisateur_id', $activeOrgId)
                ->where('organisateur_type', $activeOrgType);
        })
            ->with([
                'competition.discipline',
                'competition.category',
                'competition.evenement',
                'competition.niveau',
                'modeSaisie',
                'nbJugesOption',
                'plateau',
                'kumiteFormat',
            ])
            ->get();

        //  On mappe les données pour simplifier l'accès côté React
        $mappedConfigs = $configs->map(function ($config) {
            return [
                'id' => $config->id,
                'competition_id' => $config->competition_id,

                // Infos de l'Épreuve (Competition)
                'discipline'  => $config->competition->discipline->nom ?? '',
                'categorie'   => $config->competition->category->nom ?? '',
                'sexe'        => $config->competition->category->sexe ?? '',
                'niveau'      => $config->competition->niveau->nom ?? '',
                'heure_debut_prevu' => $config->competition->heure_debut_prevu ?? '',
                'heure_fin_prevue'  => $config->competition->heure_fin_prevue ?? '',


                // Infos de l'Événement
                'evenement_nom' => $config->competition->evenement->nom ?? '',
                'evenement_id'  => $config->competition->evenement->id ?? '',
                'date_debut'   => $config->competition->evenement->date_debut ?? '',
                'date_fin'     => $config->competition->evenement->date_fin ?? '',

                // Infos de Configuration
                'plateau_id'   => $config->plateau->id ?? '',
                'plateau_nom'   => $config->plateau->nom ?? 'Non assigné',
                'mode_saisie'   => $config->modeSaisie->libelle ?? '',
                'format'        => $config?->kumiteFormat?->code ?? '',
                'juges_option' => $config?->nbJugesOption?->valeur ?? '',
                'duree'         => $config->duration . 's',
                'est_valide'    => $config->configuration_validee,

                'raw_competition' => $config->competition,
                'raw_plateau'     => $config->plateau,
            ];
        });

        return response()->json($mappedConfigs);
    }
    public function store(Request $request)
    {

        $competition = Competition::with('discipline')
            ->find($request->competition_id);

        $estKumite = $competition?->discipline?->nom === 'Kumite';
        $request->validate([
            'competition_id'      => 'required|exists:competitions,id',
            'evenement_id'        => 'required',
            'mode_saisie_id'      => 'required',
            // plateau_id est requis SAUF si nouveau_plateau_nom est rempli
            'plateau_id'          => 'required_without:nouveau_plateau_nom',
            'nouveau_plateau_nom' => 'required_without:plateau_id|nullable|string|max:100',
            'nb_juges_option_id' => [
                'nullable',
                'exists:juge_options,id',
                Rule::requiredIf(fn() => !$estKumite),
            ],
            // 'nb_rotation'        => [
            //     'nullable',
            //     'integer',
            //     'min:1',
            //     'max:2',
            //     Rule::requiredIf(
            //         fn() =>
            //         ModeSaisie::find($request->mode_saisie_id)?->code === 'tablettes'
            //     ),
            // ],
            'kumite_format_id'   => [
                'nullable',
                'exists:kumite_formats,id',
                Rule::requiredIf(
                    fn() =>
                    $estKumite
                ),
            ],
            'duration'           => [
                'nullable',
                'integer',

                Rule::requiredIf(
                    fn() =>
                    $estKumite
                ),
            ],
        ]);

        try {
            return DB::transaction(function () use ($request) {

                $plateauId = $request->plateau_id;

                if ($request->filled('nouveau_plateau_nom')) {
                    $plateau = Plateau::create([
                        'nom'          => $request->nouveau_plateau_nom,
                        'evenement_id' => $request->evenement_id,
                    ]);
                    $plateauId = $plateau->id;
                }


                $config = ConfigNotation::updateOrCreate(
                    ['competition_id' => $request->competition_id],
                    [
                        'plateau_id'         => $plateauId,
                        'mode_saisie_id'     => $request->mode_saisie_id,
                        'kumite_format_id'   => $request->kumite_format_id,
                        'nb_juges_option_id' => $request->nb_juges_option_id,
                        'duration'           => $request->duration ?? 180,
                        'configuration_validee' => true,
                        'configure_par'      => Auth::id(),
                        'validee_a'          => now(),
                    ]
                );


                Competition::where('id', $request->competition_id)->update([
                    'statut' => 1
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Configuration enregistrée avec succès',
                    'data'    => $config->load(['plateau', 'modeSaisie'])
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la configuration : ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPlateauxByEvenement($evenementId)
    {
        try {
            $plateaux = Plateau::where('evenement_id', $evenementId)
                ->orderBy('nom', 'asc')
                ->get();


            return response()->json($plateaux, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Impossible de charger les plateaux.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
