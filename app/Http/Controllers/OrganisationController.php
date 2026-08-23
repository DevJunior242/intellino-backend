<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\League;
use App\Models\Federation;
use App\Models\ActivationKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Concerns\ResolvesTrialStatus;
use App\Http\Controllers\Concerns\ResolvesEffectifOrganisation;

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
    use ResolvesEffectifOrganisation;

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

        $nbUsers = $this->nombreUtilisateursActifs($activeId, $activeType);

        return response()->json([
            'data' => $org,
            'type' => $activeType,
            'trial' => $this->statutEssaiPour($org, $activeType),
            'palier' => $this->statutPalierPour($activeType, $nbUsers),
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

    private const TYPE_CLE = [
        'Club' => 'club',
        'Ligue' => 'league',
        'Federation' => 'federation',
    ];

    /**
     * Permet à l'admin d'une organisation créée sans clé (en essai, voir
     * ResolvesTrialStatus) de saisir sa clé une fois reçue — le seul moment
     * où une clé était consommée jusqu'ici était la création elle-même
     * (ClubController/LeagueController/FederationController::store()),
     * sans possibilité de le faire après coup.
     */
    public function activate(Request $request)
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
                'message' => "Seul l'administrateur de l'organisation peut l'activer.",
            ], 403);
        }

        if ($this->aUneCleActivee($org)) {
            return response()->json([
                'success' => false,
                'message' => 'Cette organisation a déjà consommé une clé d\'activation.',
            ], 422);
        }

        $validated = $request->validate(['activation_key' => 'required|string']);
        $typeCle = self::TYPE_CLE[$activeType] ?? null;

        return DB::transaction(function () use ($validated, $typeCle, $activeId, $activeType, $org, $request) {
            $key = ActivationKey::where('key_code', $validated['activation_key'])
                ->where('is_used', false)
                ->where('type', $typeCle)
                ->lockForUpdate()
                ->first();

            if (!$key) {
                return response()->json([
                    'success' => false,
                    'message' => "La clé d'activation est invalide ou a déjà été utilisée.",
                ], 422);
            }

            $key->update([
                'is_used' => true,
                'used_at' => now(),
                'used_by_user_id' => $request->user()->id,
                'used_by_organisation_id' => $org->id,
            ]);

            // Réactive immédiatement si l'essai avait déjà expiré (pas besoin
            // d'attendre le prochain passage de app:deactivate-expired-trials).
            if ((int) $org->status !== 1) {
                $org->update(['status' => 1]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Organisation activée avec succès.',
                'data'    => $org->fresh(),
            ]);
        });
    }
}
