<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\League;
use Illuminate\Support\Str;
use App\Models\Federation;
use Illuminate\Http\Request;
use App\Models\ActivationKey;

class ActivationKeyController extends Controller
{
    /**
     * Mappe le type de clé au modèle de l'organisation qui l'a consommée,
     * pour pouvoir afficher son nom dans la liste des clés gérées.
     */
    private function organisationModelFor(string $type): ?string
    {
        return match ($type) {
            'club' => Club::class,
            'league', 'takeover_league' => League::class,
            'federation', 'takeover_fede' => Federation::class,
            default => null,
        };
    }

    /**
     * Liste toutes les clés d'activation, avec le nom de l'organisation
     * qui les a consommées (résolu via used_by_organisation_id + type).
     */
    public function index()
    {
        $keys = ActivationKey::latest('created_at')->get();

        $organisationsByType = [];

        foreach ($keys as $key) {
            $modelClass = $this->organisationModelFor($key->type);

            if ($modelClass && $key->used_by_organisation_id) {
                $organisationsByType[$key->type][] = $key->used_by_organisation_id;
            }
        }

        $organisationsById = [];

        foreach ($organisationsByType as $type => $ids) {
            $modelClass = $this->organisationModelFor($type);
            $modelClass::whereIn('id', array_unique($ids))
                ->get(['id', 'name'])
                ->each(function ($org) use (&$organisationsById) {
                    $organisationsById[$org->id] = $org->name;
                });
        }

        $data = $keys->map(function ($key) use ($organisationsById) {
            return [
                'id' => $key->id,
                'key_code' => $key->key_code,
                'type' => $key->type,
                'comment' => $key->comment,
                'is_used' => $key->is_used,
                'used_at' => $key->used_at,
                'organisation_name' => $organisationsById[$key->used_by_organisation_id] ?? null,
                'created_at' => $key->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:league,club,federation,takeover_league,takeover_fede',
            'target_league_id' => 'nullable|exists:leagues,id',
            'comment' => 'nullable|string|max:255'
        ]);

        // Génère un format pro : LIGUE-2026-ABCD-1234
        $prefix = match ($request->type) {
            'club'       => 'CLUB',
            'league'     => 'LIGUE',
            'federation' => 'FED',
            default      => 'ORG',
        };
        $annee = now()->year;
        $random = strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));

        $keyCode = "{$prefix}-{$annee}-{$random}";

        $key = ActivationKey::create([
            'key_code' => $keyCode,
            'type' => $request->type,
            'comment' => $request->comment,
            'target_league_id' => $request->target_league_id,
            'is_used' => false
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Clé générée avec succès',
            'data' => $key
        ]);
    }
}
