<?php

namespace App\Http\Controllers;

use App\Models\Kata;
use App\Models\Inscription;
use App\Models\Competition;
use App\Models\OrdrePassage;
use App\Models\ConfigNotation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class OrdrePassageController extends Controller
{

    public function index($configId)
    {

        $orders = OrdrePassage::with(
            [
                'inscription.athlete:id,fullname',
                'inscription.organisateur:id,name',
                'inscription.competition.category:id,nom,sexe',
                'inscription.kataTeam:id,inscription_id,nom',
                'kata:id,nom',
            ]
        )
            ->withCount('inscription')
            ->whereHas('inscription', function ($q) {
                $q->where('status', Inscription::STATUS_VALIDE);
            })
            ->where('config_notation_id', $configId)
            ->orderBy('ordre', 'asc')
            ->get();

        return response()->json($orders);
    }

    //nnon assign
    public function getNonAssignees($competitionId)
    {
        $competition = Competition::with(['category:id,poids_min,poids_max', 'subDiscipline:id,nom'])
            ->findOrFail($competitionId);

        // On récupère les IDs des inscriptions déjà présentes dans une file d'attente (ordre_passages)
        $dejaAssigneesIds = OrdrePassage::pluck('inscription_id');

        // On retourne les inscriptions validées de cette compétition qui NE SONT PAS dans cette liste
        $inscriptions = Inscription::with(['athlete:id,fullname', 'organisateur:id,name', 'kataTeam:id,inscription_id,nom'])
            ->where('competition_id', $competitionId)
            ->where('status', Inscription::STATUS_VALIDE)
            ->whereNotIn('id', $dejaAssigneesIds)
            ->get();

        return response()->json([
            'groups' => $this->grouperParPoids($competition, $inscriptions),
        ]);
    }

    // En Kumite, répartit les inscrits en tranches de poids égales (bornées par la
    // catégorie) — une tranche par tatami configuré pour l'épreuve — afin d'aider
    // à équilibrer les plateaux. En Kata (ou catégorie sans poids_min/poids_max),
    // on retourne un unique groupe non étiqueté.
    private function grouperParPoids(Competition $competition, $inscriptions): array
    {
        $isKumite = strtolower($competition->subDiscipline?->nom ?? '') === 'kumite';
        $poidsMin = $competition->category?->poids_min;
        $poidsMax = $competition->category?->poids_max;

        if (!$isKumite || is_null($poidsMin) || is_null($poidsMax) || $poidsMax <= $poidsMin) {
            return [[
                'label'         => null,
                'poids_min'     => null,
                'poids_max'     => null,
                'inscriptions'  => $inscriptions->values(),
            ]];
        }

        $nbGroupes = max(1, $competition->configNotations()->count());
        $largeur = ($poidsMax - $poidsMin) / $nbGroupes;

        $groupes = [];
        for ($i = 0; $i < $nbGroupes; $i++) {
            $min = round($poidsMin + $i * $largeur, 1);
            $max = round($i === $nbGroupes - 1 ? $poidsMax : $poidsMin + ($i + 1) * $largeur, 1);
            $groupes[] = [
                'label'        => "{$min}-{$max}kg",
                'poids_min'    => $min,
                'poids_max'    => $max,
                'inscriptions' => [],
            ];
        }

        $sansPoids = [];
        foreach ($inscriptions as $inscription) {
            $poids = $inscription->poids_declare;
            if (is_null($poids)) {
                $sansPoids[] = $inscription;
                continue;
            }

            $index = $largeur > 0
                ? (int) min($nbGroupes - 1, floor(($poids - $poidsMin) / $largeur))
                : 0;
            $groupes[$index]['inscriptions'][] = $inscription;
        }

        if (!empty($sansPoids)) {
            $groupes[] = [
                'label'        => 'Poids non renseigné',
                'poids_min'    => null,
                'poids_max'    => null,
                'inscriptions' => $sansPoids,
            ];
        }

        return $groupes;
    }

    // Assigner
    public function assigner(Request $request)
    {
        $request->validate([
            'config_notation_id' => 'required|exists:config_notations,id',
            'inscription_id'     => 'required|exists:inscriptions,id',
            'kata_id'            => 'nullable|exists:katas,id',
        ]);

        $configId = $request->config_notation_id;
        $inscriptionId = $request->inscription_id;

        // Kata WKF Art. 5.1 : seul un kata du catalogue officiel actif peut être choisi.
        if ($request->filled('kata_id')) {
            $config = ConfigNotation::findOrFail($configId);

            if ($config->estKata() && !Kata::where('id', $request->kata_id)->where('actif', true)->exists()) {
                return response()->json(['message' => 'Kata invalide ou inactif.'], 422);
            }
        }

        // On calcule le prochain numéro d'ordre pour ce tatami
        $dernierOrdre = OrdrePassage::where('config_notation_id', $configId)->max('ordre') ?? 0;

        $donnees = [
            'config_notation_id' => $configId,
            'ordre' => $dernierOrdre + 1,
            'status' => OrdrePassage::STATUS_NOT_STARTED,
        ];

        if ($request->filled('kata_id')) {
            $donnees['kata_id'] = $request->kata_id;
        }

        return OrdrePassage::updateOrCreate(
            ['inscription_id' => $inscriptionId],
            $donnees
        );
    }

    // Retirer
    public function retirer($inscriptionId)
    {
        $order = OrdrePassage::where('inscription_id', $inscriptionId)->first();

        if (!$order) {
            return response()->json(['message' => 'Ordre de passage introuvable'], 422);
        }

        $order->delete();
        return response()->json(['message' => 'Retiré avec succès']);
    }
}
