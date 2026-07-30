<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\League;
use App\Models\Federation;
use Illuminate\Http\Request;

class SuperAdminOrganizationController extends Controller
{
    private function modelFor(string $type): ?string
    {
        return match ($type) {
            'club' => Club::class,
            'league' => League::class,
            'federation' => Federation::class,
            default => null,
        };
    }

    /**
     * Vue d'ensemble complète de toutes les organisations de la plateforme,
     * pour le contrôle total du super admin.
     */
    public function overview()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'clubs' => Club::withCount('users')
                    ->orderBy('name')
                    ->get(['id', 'name', 'city', 'region', 'status', 'league_id', 'created_at']),
                'leagues' => League::withCount('users')
                    ->orderBy('name')
                    ->get(['id', 'name', 'region', 'status', 'federation_id', 'created_at']),
                'federations' => Federation::withCount('users')
                    ->orderBy('name')
                    ->get(['id', 'name', 'code', 'status', 'created_at']),
            ],
        ]);
    }

    /**
     * Active/désactive une organisation (club, ligue ou fédération).
     * Une organisation désactivée devient inaccessible à ses membres
     * (voir CheckClubRole::handle) — le super admin, lui, garde toujours accès.
     */
    public function toggleStatus(Request $request, string $type, string $id)
    {
        $modelClass = $this->modelFor($type);

        if (!$modelClass) {
            return response()->json([
                'success' => false,
                'message' => 'Type d\'organisation invalide.',
            ], 422);
        }

        $org = $modelClass::findOrFail($id);
        $org->status = $org->status ? 0 : 1;
        $org->save();

        return response()->json([
            'success' => true,
            'message' => $org->status ? 'Organisation réactivée.' : 'Organisation désactivée.',
            'data' => $org,
        ]);
    }
}
