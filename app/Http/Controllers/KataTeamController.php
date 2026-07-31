<?php

namespace App\Http\Controllers;

use App\Models\Inscription;
use App\Models\KataTeam;
use App\Models\KataTeamMembre;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreKataTeamReq;

class KataTeamController extends Controller
{
    // Inscrit une équipe de Kata (3-4 athlètes, Art. 3.5 WKF) : crée une
    // Inscription "porteuse" (capitaine en athlete_id) + le KataTeam associé.
    public function store(StoreKataTeamReq $request)
    {
        $validated = $request->validated();
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier le club connecté.',
            ], 403);
        }

        $studentIds = collect($validated['membres'])->pluck('student_id')->all();

        $dejaIndividuel = Inscription::where('competition_id', $validated['competition_id'])
            ->whereIn('athlete_id', $studentIds)
            ->exists();

        $dejaEnEquipe = KataTeamMembre::whereIn('student_id', $studentIds)
            ->whereHas('kataTeam.inscription', fn ($q) => $q->where('competition_id', $validated['competition_id']))
            ->exists();

        if ($dejaIndividuel || $dejaEnEquipe) {
            return response()->json([
                'success' => false,
                'message' => 'Un ou plusieurs élèves sont déjà inscrits à cette épreuve.',
            ], 422);
        }

        $capitaine = collect($validated['membres'])->first(fn ($m) => empty($m['est_reserve'])) ?? $validated['membres'][0];

        $kataTeam = DB::transaction(function () use ($validated, $activeId, $activeType, $capitaine) {
            $ordre = Inscription::where('competition_id', $validated['competition_id'])
                ->lockForUpdate()
                ->max('ordre_passage') ?? 0;

            $inscription = Inscription::create([
                'competition_id'    => $validated['competition_id'],
                'organisateur_id'   => $activeId,
                'organisateur_type' => $activeType,
                'athlete_id'        => $capitaine['student_id'],
                'ordre_passage'     => $ordre + 1,
            ]);

            $kataTeam = KataTeam::create([
                'inscription_id' => $inscription->id,
                'nom'            => $validated['nom'],
            ]);

            foreach ($validated['membres'] as $membre) {
                KataTeamMembre::create([
                    'kata_team_id' => $kataTeam->id,
                    'student_id'   => $membre['student_id'],
                    'est_reserve'  => $membre['est_reserve'] ?? false,
                ]);
            }

            return $kataTeam;
        });

        return response()->json([
            'success'  => true,
            'message'  => 'Équipe inscrite avec succès',
            'kataTeam' => $kataTeam->load(['inscription', 'membres.student:id,fullname']),
        ], 201);
    }
}
