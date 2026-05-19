<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Events\TatamiUpdated;
use App\Models\ConfigNotation;
use App\Models\RotationArbitre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RotationArbitreController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'config_notation_id'     => 'required|exists:config_notations,id',
            'arbitre_competition_id' => 'required|exists:arbitre_competitions,id',
        ]);

        try {
            // On vérifie si l'arbitre n'est pas déjà sur ce tatami
            $exists = RotationArbitre::where('config_notation_id', $request->config_notation_id)
                ->where('arbitre_competition_id', $request->arbitre_competition_id)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Cet arbitre est déjà affecté à ce plateau'], 422);
            }

            $dernierOrdre = RotationArbitre::where('config_notation_id', $request->config_notation_id)->max('ordre') ?? 0;

            $rotation = RotationArbitre::create([
                'id'                     => (string) Str::uuid(),
                'config_notation_id'     => $request->config_notation_id,
                'arbitre_competition_id' => $request->arbitre_competition_id,
                'est_superviseur'        => false,
                'ordre'                  => $dernierOrdre + 1,
                'actif'                  => false,
                'poste'                  => null,
            ]);
            broadcast(new TatamiUpdated($rotation->config_notation_id))->toOthers();
            return response()->json(['success' => true, 'data' => $rotation->load('arbitreCompetition.user')], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * ÉTAPE 2 : Désigner (ou changer) le superviseur du tatami
     */
    public function designerSuperviseur(Request $request, $configId)
    {
        $request->validate([
            'arbitre_competition_id' => 'required|exists:arbitre_competitions,id'
        ]);

        return DB::transaction(function () use ($request, $configId) {
            // 1. On retire le badge superviseur à TOUT LE MONDE sur cette config
            $start = microtime(true);

            RotationArbitre::where('config_notation_id', $configId)
                ->update(['est_superviseur' => false]);

            // 2. On l'attribue à l'élu
            RotationArbitre::where('config_notation_id', $configId)
                ->where('arbitre_competition_id', $request->arbitre_competition_id)
                ->update([
                    'est_superviseur' => true,
                    'poste' => null,
                    'actif' => true
                ]);
            broadcast(new TatamiUpdated($configId))->toOthers();
            Log::info('Temps execution', ['ms' => round((microtime(true) - $start) * 1000, 2)]);
            return response()->json(['success' => true, 'message' => 'Superviseur désigné avec succès']);
        });
    }

    public function update(Request $request)
    {
        $start = microtime(true);
        $request->validate([
            'rotation_id' => 'required|exists:rotation_arbitres,id',
            'poste'       => 'nullable|integer|min:1|max:7', // null pour remettre au banc
        ]);

        $rotation = RotationArbitre::find($request->rotation_id);

        // Si on veut mettre un poste, on vérifie qu'il n'est pas déjà pris
        if ($request->poste) {
            $dejaPris = RotationArbitre::where('config_notation_id', $rotation->config_notation_id)
                ->where('poste', $request->poste)
                ->exists();

            if ($dejaPris) {
                return response()->json(['message' => 'Ce poste est déjà occupé'], 422);
            }
        }

        $rotation->update([
            'poste' => $request->poste,
            'actif' => !is_null($request->poste)
        ]);
        broadcast(new TatamiUpdated($rotation->config_notation_id))->toOthers();
        Log::info('Temps execution', ['ms' => round((microtime(true) - $start) * 1000, 2)]);
        return response()->json(['success' => true]);
    }
}
