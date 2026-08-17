<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\League;
use App\Models\Student;
use App\Models\Evenement;
use App\Models\Competition;

use App\Models\Inscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInscriptionReq;

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
                'organisateur',
                'competition.category:id,nom,sexe,poids_min,poids_max',
                'competition.subDiscipline:id,nom',
                'kata:id,nom'
            ])
            ->orderBy('organisateur_id')
            ->get();

        return response()->json($inscriptions);
    }


    public function getEvenementsOuverts(Request $request)
    {

        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        // Un organisateur voit les événements de sa propre structure ainsi
        // que ceux organisés au-dessus de lui dans la hiérarchie (un club
        // voit aussi les événements de sa ligue et de sa fédération ; une
        // ligue voit aussi ceux de sa fédération). $activeId n'est un club
        // que si l'appelant est effectivement un Club — sinon Club::where()
        // ne matcherait jamais rien, ce qui plantait la recherche pour une
        // Ligue/Fédération inscrivant directement ses athlètes.
        $clubId = $activeType === 'Club' ? $activeId : null;
        $leagueId = $activeType === 'Ligue' ? $activeId : null;
        $federationId = $activeType === 'Federation' ? $activeId : null;

        if ($activeType === 'Club') {
            $club = Club::where('id', $clubId)->first(['league_id']);
            $leagueId = $club?->league_id;
            $federationId = $leagueId ? League::where('id', $leagueId)->value('federation_id') : null;
        } elseif ($activeType === 'Ligue') {
            $federationId = League::where('id', $activeId)->value('federation_id');
        }

        $evenements = Evenement::where('status', Evenement::STATUT_EN_COURS)
            ->where(function ($q) use ($clubId, $leagueId, $federationId) {
                $q->where(function ($q2) use ($clubId) {
                    $q2->where('organisateur_type', 'Club')
                        ->where('organisateur_id', $clubId);
                })
                    ->orWhere(function ($q2) use ($leagueId) {
                        $q2->where('organisateur_type', 'Ligue')
                            ->where('organisateur_id', $leagueId);
                    })
                    ->orWhere(function ($q2) use ($federationId) {
                        $q2->where('organisateur_type', 'Federation')
                            ->where('organisateur_id', $federationId);
                    });
            })
            ->with([
                'competitions' => function ($q) {
                    $q->with(['category:id,nom,sexe', 'subDiscipline:id,nom', 'niveau:id,nom'])
                        ->withCount('inscriptions');
                }
            ])
            ->orderByDesc('created_at')->get();
        return response()->json([
            'success'    => true,
            'evenements' => $evenements,
        ]);
    }

    // Récupérer les inscriptions d'une épreuve pour un club
    public function parEpreuve(Competition $competition, Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $inscriptions = Inscription::where('competition_id', $competition->id)
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->with(['athlete', 'kata:id,nom'])
            ->get();

        return response()->json([
            'success'      => true,
            'inscriptions' => $inscriptions,
            'epreuve'      => $competition->load(['category:id,nom,sexe,poids_min,poids_max', 'subDiscipline:id,nom', 'niveau:id,nom']),
        ]);
    }

    // Élèves du club éligibles à une épreuve (bonne catégorie, sexe, pas déjà
    // inscrits) — utilisé pour peupler la liste à cocher d'InscriptionForm
    public function studentsDisponibles(Competition $competition, Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier l\'organisateur connecté.'
            ], 403);
        }

        $competition->loadMissing('category');
        $category = $competition->category;

        $dejaInscrits = Inscription::where('competition_id', $competition->id)
            ->pluck('athlete_id');

        // Critères d'éligibilité (âge/sexe de la catégorie + pas déjà
        // inscrit) partagés entre la recherche "via club" et la recherche
        // "athlète indépendant" ci-dessous.
        $appliquerFiltres = function ($q) use ($dejaInscrits, $category) {
            $q->whereNotIn('students.id', $dejaInscrits)
                ->when($category && $category->sexe !== 'Mixte', function ($q2) use ($category) {
                    $q2->where('students.sex', $category->sexe);
                })
                ->when($category && !is_null($category->age_min) && !is_null($category->age_max), function ($q2) use ($category) {
                    $aujourdhui = \Carbon\Carbon::now();
                    $dateNaissanceMin = $aujourdhui->copy()->subYears($category->age_max)->format('Y-m-d');
                    $dateNaissanceMax = $aujourdhui->copy()->subYears($category->age_min)->format('Y-m-d');
                    $q2->whereBetween('students.birthdate', [$dateNaissanceMin, $dateNaissanceMax]);
                });
            return $q;
        };

        // 1) Athlètes rattachés à un club dans le ressort de l'organisateur
        // (hiérarchie club -> ligue -> fédération).
        $viaClub = Student::query()
            ->join('club_students', 'club_students.student_id', '=', 'students.id')
            ->whereNull('club_students.deleted_at'); // Exclure si l'élève a été retiré du club

        if ($activeType === 'Club') {
            $viaClub->where('club_students.club_id', $activeId);
        } elseif ($activeType === 'Ligue') {
            $viaClub->join('clubs', 'clubs.id', '=', 'club_students.club_id')
                ->where('clubs.league_id', $activeId);
        } elseif ($activeType === 'Federation') {
            $liguesIds = League::where('federation_id', $activeId)->pluck('id');
            $viaClub->join('clubs', 'clubs.id', '=', 'club_students.club_id')
                ->whereIn('clubs.league_id', $liguesIds);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Organisateur non reconnu.'
            ], 403);
        }

        $students = $appliquerFiltres($viaClub)
            ->select('students.id', 'students.fullname', 'students.birthdate', 'students.sex')
            ->distinct()
            ->get();

        // 2) Athlètes indépendants (sans club) inscrits directement par cet
        // organisateur — ou par une de ses ligues, si on est la fédération.
        // Un Club n'a pas d'athlètes indépendants à lui : tous ses
        // licenciés passent par club_students.
        if ($activeType !== 'Club') {
            $organisateurs = [[$activeType, $activeId]];
            if ($activeType === 'Federation') {
                foreach (League::where('federation_id', $activeId)->pluck('id') as $ligueId) {
                    $organisateurs[] = ['Ligue', $ligueId];
                }
            }

            $independants = Student::query()->where(function ($q) use ($organisateurs) {
                foreach ($organisateurs as [$type, $id]) {
                    $q->orWhere(function ($q2) use ($type, $id) {
                        $q2->where('organisateur_type', $type)->where('organisateur_id', $id);
                    });
                }
            });

            $independants = $appliquerFiltres($independants)
                ->select('students.id', 'students.fullname', 'students.birthdate', 'students.sex')
                ->get();

            $students = $students->concat($independants);
        }

        $students = $students->unique('id')->sortBy('fullname')->values();

        return response()->json([
            'success'  => true,
            'students' => $students,
            'category' => $category ? $category->only(['id', 'nom', 'sexe', 'poids_min', 'poids_max']) : null,
        ]);
    }

    // Inscrire plusieurs athlètes à une épreuve en une seule fois
    public function store(StoreInscriptionReq $request)
    {
        $validated = $request->validated();
        $competitionId = $validated['competition_id'];
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $athleteIds = collect($validated['inscriptions'])->pluck('athlete_id')->all();

        // Ignorer les athlètes déjà inscrits à cette épreuve
        $dejaInscrits = Inscription::where('competition_id', $competitionId)
            ->whereIn('athlete_id', $athleteIds)
            ->pluck('athlete_id')
            ->all();

        $aInscrire = collect($validated['inscriptions'])
            ->reject(fn($i) => in_array($i['athlete_id'], $dejaInscrits))
            ->values();

        if ($aInscrire->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tous les athlètes sélectionnés sont déjà inscrits à cette épreuve',
            ], 422);
        }

        $inscriptions = DB::transaction(function () use ($competitionId, $activeId, $activeType, $aInscrire) {
            $ordre = Inscription::where('competition_id', $competitionId)
                ->lockForUpdate()
                ->max('ordre_passage') ?? 0;

            $ids = [];
            foreach ($aInscrire as $i) {
                $inscription = Inscription::create([
                    'competition_id'    => $competitionId,
                    'organisateur_id'   => $activeId,
                    'organisateur_type' => $activeType,
                    'athlete_id'        => $i['athlete_id'],
                    'poids_declare'     => $i['poids_declare'] ?? null,
                    'kata_id'           => $i['kata_id'] ?? null,
                    'ordre_passage'     => ++$ordre,
                ]);
                $ids[] = $inscription->id;
            }

            return Inscription::whereIn('id', $ids)->with(['athlete', 'kata:id,nom'])->get();
        });

        $nbIgnores = count($dejaInscrits);
        $message = $inscriptions->count() . ' athlète(s) inscrit(s) avec succès';
        if ($nbIgnores > 0) {
            $message .= " ({$nbIgnores} déjà inscrit(s) ignoré(s))";
        }

        return response()->json([
            'success'      => true,
            'message'      => $message,
            'inscriptions' => $inscriptions,
        ], 201);
    }
    //valider une inscription (pesée officielle)
    public function valider(Inscription $inscription, Request $request)
    {
        $validated = $request->validate([
            'poids_officiel' => 'nullable|numeric|min:0',
        ]);

        $inscription->update([
            'status'         => Inscription::STATUS_VALIDE,
            'poids_officiel' => $validated['poids_officiel'] ?? $inscription->poids_officiel,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inscription validée',
        ]);
    }
    //annuler une inscription (pesée officielle échouée)
    public function annuler(Inscription $inscription, Request $request)
    {
        $validated = $request->validate([
            'poids_officiel' => 'nullable|numeric|min:0',
        ]);

        $inscription->update([
            'status'         => Inscription::STATUS_ECHOUE,
            'poids_officiel' => $validated['poids_officiel'] ?? $inscription->poids_officiel,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inscription annulée',
        ]);
    }

    // Désinscrire un athlète (uniquement par le club inscripteur, avant validation par la ligue)
    public function destroy(Inscription $inscription, Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($inscription->organisateur_id !== $activeId || $inscription->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => "Vous n'êtes pas autorisé à retirer cette inscription.",
            ], 403);
        }

        if ($inscription->status === Inscription::STATUS_VALIDE) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de retirer une inscription déjà validée par la ligue.',
            ], 422);
        }

        $inscription->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inscription retirée',
        ]);
    }
}
