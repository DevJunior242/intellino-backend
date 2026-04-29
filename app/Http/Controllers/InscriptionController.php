<?php

namespace App\Http\Controllers;

use App\Models\Evenement;
use App\Models\Competition;
use App\Models\Inscription;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

class InscriptionController extends Controller
{

    public function index(Request $request)
    {
        $request->validate([
            'competition_id' => 'required|exists:competitions,id'
        ]);

        // On récupère TOUTES les inscriptions pour CETTE compétition
        // On charge les relations pour avoir le nom du club et de l'athlète
        $inscriptions = Inscription::where('competition_id', $request->competition_id)
            ->with([
                'athlete:id,fullname,sex',
                'club:id,name',
                'category:id,nom,sexe',
                'disciplineleague:id,nom'
            ])
            ->orderBy('club_id')
            ->get();

        return response()->json($inscriptions);
    }
    // public function store(InscriptionRequest $request): JsonResponse
    // {
    //     $clubId = $request->validated_club_id;
    //     Log::info("clubId", ['clubId' => $clubId]);

    //     $inscription = DB::transaction(function () use ($request, $clubId) {

    //         // Lock — bloque les autres transactions
    //         $dernierOrdre = Inscription::where('competition_id', $request->competition_id)
    //             ->lockForUpdate()
    //             ->max('ordre_passage') ?? 0;

    //         return Inscription::create([
    //             'competition_id'      => $request->competition_id,
    //             'athlete_id'          => $request->athlete_id,

    //             'poids_declare'       => $request->poids_declare,
    //             'statut_pesee'        => $request->statut_pesee,
    //             'ordre_passage'       => $dernierOrdre + 1,
    //             'club_id'             => $clubId,
    //             'statut_passage'      => 'en_attente',
    //         ]);
    //     });
    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Athlète inscrit avec succès à la compétition !',
    //         'data' => $inscription->load(['athlete', 'category'])
    //     ], 201);
    // }

    // public function getByCompetition(Request $request, $competitionId): JsonResponse
    // {
    //     $clubId = $request->validated_club_id;

    //     $inscriptions = Inscription::where('competition_id', $competitionId)
    //         ->where('club_id', $clubId)
    //         ->with(['athlete:id,fullname,sex', 'category:id,nom,sexe', 'disciplineleague:id,nom'])
    //         ->get();

    //     return response()->json([
    //         'inscriptions' => $inscriptions
    //     ]);
    // }

    // public function validerPesee(Request $request, $id)
    // {
    //     $request->validate([
    //         'poids_officiel' => 'required|numeric|between:10,200',
    //         'statut_pesee' => 'required|in:1,2', // 1=Validé, 2=Échoué
    //     ]);

    //     $inscription = Inscription::findOrFail($id);
    //     $inscription->update([
    //         'poids_officiel' => $request->poids_officiel,
    //         'statut_pesee' => $request->statut_pesee,
    //     ]);

    //     return response()->json(['message' => 'Pesée enregistrée !']);
    // }



    // // Assigner athlète à un tatami
    // public function assignerTatami(Request $request, Inscription $inscription)
    // {
    //     $validated = $request->validate([
    //         'config_notation_id' => [
    //             'nullable',
    //             Rule::exists('config_notations', 'id')
    //                 ->where('competition_id', $inscription->competition_id),
    //         ],
    //     ]);

    //     $inscription->update($validated);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Athlète assigné au tatami',
    //         'data'    => $inscription->fresh(),
    //     ]);
    // }

    // // Athlètes non assignés
    // public function nonAssignees(Competition $competition)
    // {
    //     $inscriptions = Inscription::where('competition_id', $competition->id)
    //         ->whereNull('config_notation_id')
    //         ->with(['athlete:id,fullname', 'club:id,name', 'kata:id,nom'])
    //         ->orderBy('ordre_passage')
    //         ->get();

    //     return response()->json([
    //         'success' => true,
    //         'data'    => $inscriptions,
    //     ]);
    // }

    // // Athlètes d'un tatami
    // public function parTatami(ConfigNotation $config)
    // {
    //     $inscriptions = Inscription::where('config_notation_id', $config->id)
    //         ->with(['athlete:id,fullname', 'club:id,name', 'kata:id,nom'])
    //         ->orderBy('ordre_passage')
    //         ->get();

    //     return response()->json([
    //         'success' => true,
    //         'data'    => $inscriptions,
    //         'total'   => $inscriptions->count(),
    //     ]);
    // }

    public function getEvenementsOuverts()
    {
        $evenements = Evenement::with([
            'competitions' => function ($q) {
                $q->with(['category', 'discipline', 'niveau'])
                    ->withCount('inscriptions');
            },
        ])
            ->whereHas('competitions')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success'    => true,
            'evenements' => $evenements,
        ]);
    }

    // Récupérer les inscriptions d'une épreuve pour un club
    public function parEpreuve(Competition $competition, Request $request)
    {
        $inscriptions = Inscription::where('competition_id', $competition->id)
            ->where('club_id', $request->club_id)
            ->with(['athlete', 'kata'])
            ->get();

        return response()->json([
            'success'      => true,
            'inscriptions' => $inscriptions,
            'epreuve'      => $competition->load(['category', 'discipline', 'niveau']),
        ]);
    }

    // Inscrire un athlète à une épreuve
    public function store(Request $request)
    {
        $validated = $request->validate([
            'competition_id'      => 'required|exists:competitions,id',
            'athlete_id'          => 'required|exists:students,id',
            'poids_declare'       => 'nullable|numeric|min:0',
            'kata_id'             => [
                'nullable',
                'exists:katas,id',
                // obligatoire si discipline = kata
                \Illuminate\Validation\Rule::requiredIf(function () use ($request) {
                    $comp = Competition::with('discipline')->find($request->competition_id);
                    return strtolower($comp?->discipline?->nom) === 'kata';
                }),
            ],
        ]);

        // Vérifier doublon
        $existe = Inscription::where('competition_id', $validated['competition_id'])
            ->where('athlete_id', $validated['athlete_id'])
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Cet athlète est déjà inscrit à cette épreuve',
            ], 422);
        }

        // Ordre de passage automatique
        $dernierOrdre = Inscription::where('competition_id', $validated['competition_id'])
            ->lockForUpdate()
            ->max('ordre_passage') ?? 0;

        $inscription = Inscription::create([
            'competition_id'  => $validated['competition_id'],
            'athlete_id'      => $validated['athlete_id'],
            'club_id'         => $request->club_id,
            'poids_declare'   => $validated['poids_declare'],
            'kata_id'         => $validated['kata_id'] ?? null,
            'ordre_passage'   => $dernierOrdre + 1,
            'statut_pesee'    => 0,
            'statut_passage'  => 'en_attente',
        ]);

        return response()->json([
            'success'     => true,
            'message'     => 'Athlète inscrit avec succès',
            'inscription' => $inscription->load(['athlete', 'kata']),
        ], 201);
    }

    // Désinscrire un athlète
    public function destroy(Inscription $inscription)
    {
        $inscription->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inscription annulée',
        ]);
    }
}
