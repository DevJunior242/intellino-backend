<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\Licence;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLicenceReq;

class LicenceController extends Controller
{

    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $search = $request->search;
        $status = $request->status;
        $saisonActive =  Saison::where('active', true)->first();

        $licenses = Licence::with(['student', 'club'])
            ->where('saison_id', $saisonActive->id)
            ->where('league_id', $activeId)
            ->when($status, fn($q) => $q->where('status', $status))
            ->when(
                $search,
                fn($q) => $q
                    ->where('numero', 'like', "%$search%")
                    ->orWhereHas(
                        'student',
                        fn($sq) =>
                        $sq->where('fullname', 'like', "%$search%")
                    )
            )
            ->latest()
            ->paginate(10);

        return response()->json($licenses);
    }




    public function LicenceStat()
    {
        $user = auth()->user();
        $activeId = $user->current_league_id;
        Log::info('activeId$activeId', ['activeId$activeId' => $activeId]);
        if (!$activeId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être dans une ligue pour accéder à ses clubs',
            ], 400);
        }
        //licences actives count 
        $activeLicences = Licence::where('league_id', $activeId)
            ->where('date_expiration', '>=', now())
            ->count();

        //licences expirees count
        $expiredLicences = Licence::where('league_id', $activeId)
            ->where('date_expiration', '<', now())
            ->count();
        //licence en attente count
        $pendingLicences = Licence::where('league_id', $activeId)
            ->where('status', Licence::STATUS_ACTIVE)
            ->count();


        //somme des montants des licences actives
        $totalActiveLicences = Licence::where('league_id', $activeId)
            ->where('date_expiration', '>=', now())
            ->sum('montant');
        $totalActiveLicences = $totalActiveLicences / 100;

        return response()->json([
            'success' => true,
            'message' => 'Licences list',
            'active' => $activeLicences,
            'expired' => $expiredLicences,
            'pending' => $pendingLicences,
            'total_active' => $totalActiveLicences,
        ]);
    }




    public function store(StoreLicenceReq $request)
    {

        $activeId = $request->attributes->get('organisateur_id');
        if (!$activeId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être dans une ligue pour créer une licence',
            ], 422);
        }
        // Validation des données
        $validated = $request->validated();
        $saisonActive =  Saison::where('active', true)->first();

        // 1. Génération du numéro de licence unique 
        $annee = explode('-', $saisonActive->libelle)[0];
        $validated['numero'] = $this->generateLicenceNumber($annee);

        $student = Student::with('currentGrade.grade')->findOrFail($validated['student_id']);
        $gradeName = $student?->currentGrade?->grade->name;
        if (!$activeId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez définir une saison pour créer une licence',
            ], 422);
        }



        $licence = Licence::create($validated + [
            'club_id' => $request->input('club_id'),
            'league_id' => $activeId,
            'grade_au_moment' => $gradeName,
            'saison_id' => $saisonActive->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Licence générée avec succès',
            'data' => $licence
        ], 201);
    }

    /**
     * Génère un numéro de type LIG-2025-00001
     */
    private function generateLicenceNumber($year): string
    {
        $lastLicence = Licence::where('numero', 'LIKE', "LIG-$year-%")
            ->orderBy('numero', 'desc')
            ->first();

        if (!$lastLicence) {
            $number = 1;
        } else {
            // On extrait le dernier nombre (ex: de LIG-2025-00042 on prend 42)
            $lastNumber = (int) substr($lastLicence->numero, -5);
            $number = $lastNumber + 1;
        }

        return 'LIG-' . $year . '-' . str_pad($number, 5, '0', STR_PAD_LEFT);
    }
}
