<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\League;
use App\Models\Federation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Concerns\ResolvesTrialStatus;

/**
 * Auto-édition d'une organisation (Club, Ligue ou Fédération) par son propre
 * admin : nom, coordonnées, logo, langue/devise. Toujours scopé sur
 * l'organisation active du token (organisateur_id/organisateur_type posés
 * par le middleware "permission") — jamais un id arbitraire pris dans
 * l'URL, pour ne pas permettre à un admin de club A d'éditer le club B.
 */
class OrganisationController extends Controller
{
    use ResolvesTrialStatus;

    private function resolve(?string $activeId, ?string $activeType)
    {
        if (!$activeId) {
            return null;
        }

        return match ($activeType) {
            'Club' => Club::find($activeId),
            'Ligue' => League::find($activeId),
            'Federation' => Federation::find($activeId),
            default => null,
        };
    }

    private function rulesPour(string $activeType): array
    {
        $communes = [
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'logo'    => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'langue'  => 'nullable|string|max:5',
            'devise'  => 'nullable|string|max:10',
        ];

        return match ($activeType) {
            'Club' => $communes + [
                'city'   => 'nullable|string|max:255',
                'region' => 'nullable|string|max:255',
            ],
            'Ligue' => $communes + [
                'region' => 'nullable|string|max:255',
            ],
            'Federation' => $communes + [
                'phone'   => 'nullable|string|max:50',
                'email'   => 'nullable|email|max:255',
                'website' => 'nullable|string|max:255',
            ],
            default => $communes,
        };
    }

    public function show(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $org = $this->resolve($activeId, $activeType);

        if (!$org) {
            return response()->json(['message' => 'Organisation introuvable.'], 404);
        }

        return response()->json([
            'data' => $org,
            'type' => $activeType,
            'trial' => $this->statutEssaiPour($org),
        ]);
    }

    public function update(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $role = $request->attributes->get('role');

        $org = $this->resolve($activeId, $activeType);

        if (!$org) {
            return response()->json(['message' => 'Organisation introuvable.'], 404);
        }

        if ($role !== 'admin') {
            return response()->json([
                'message' => "Seul l'administrateur de l'organisation peut en modifier les informations.",
            ], 403);
        }

        $validated = $request->validate($this->rulesPour($activeType));

        if ($request->hasFile('logo')) {
            if ($org->logo) {
                Storage::disk('public')->delete($org->logo);
            }
            $file = $validated['logo'];
            $ext = $file->getClientOriginalExtension();
            $validated['logo'] = $file->storeAs('logos', uniqid() . '.' . $ext, 'public');
        } else {
            unset($validated['logo']);
        }

        $org->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Informations mises à jour avec succès.',
            'data'    => $org->fresh(),
        ]);
    }
}
