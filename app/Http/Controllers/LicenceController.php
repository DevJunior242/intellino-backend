<?php

namespace App\Http\Controllers;

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

        $search = $request->search;
        $status = $request->status;

        $licenses = Licence::with(['student', 'club'])
            ->when($status, function ($query) use ($status) {
                $query->where('statut', $status);
            })
            ->when($search, function ($query) use ($search) {
                $query->where('numero', 'like', "%$search%")
                    ->orWhereHas('student', function ($q) use ($search) {
                        $q->where('nom', 'like', "%$search%");
                    });
            })
            ->latest()
            ->paginate(10);

        return response()->json($licenses);
    }




    public function LicenceStat()
    {
        $user = auth()->user();
        $leagueId = $user->current_league_id;
        Log::info('leagueId', ['leagueId' => $leagueId]);
        if (!$leagueId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être dans une ligue pour accéder à ses clubs',
            ], 400);
        }
        //licences actives count 
        $activeLicences = Licence::where('league_id', $leagueId)
            ->where('date_expiration', '>=', now())
            ->count();

        //licences expirees count
        $expiredLicences = Licence::where('league_id', $leagueId)
            ->where('date_expiration', '<', now())
            ->count();
        //licence en attente count
        $pendingLicences = Licence::where('league_id', $leagueId)
            ->where('statut', 'pending')
            ->count();


        //somme des montants des licences actives
        $totalActiveLicences = Licence::where('league_id', $leagueId)
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

        $user = auth()->user();
        $leagueId = $user->current_league_id;
        Log::info('leagueId', ['leagueId' => $leagueId]);
        if (!$leagueId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être dans une ligue pour créer une licence',
            ], 400);
        }
        // Validation des données
        $validated = $request->validated();

        // 1. Génération du numéro de licence unique 
        $annee = explode('-', $validated['saison'])[0]; // On prend 2025 de "2025-2026"
        $validated['numero'] = $this->generateLicenceNumber($annee);

        $student = Student::with('currentGrade.grade')->findOrFail($validated['student_id']);
        $gradeName = $student->currentGrade->grade->name;
        Log::info('gradeName', ['gradeName' => $gradeName]);
        $validated['grade_au_moment'] = $gradeName;
        $licence = Licence::create($validated + [
            'club_id' => $request->validated_club_id,
            'league_id' => $leagueId,
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
