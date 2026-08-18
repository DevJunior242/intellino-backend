<?php


namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Saison;
use App\Models\League;
use App\Models\Federation;
use App\Models\Affiliation;
use Illuminate\Http\Request;
use App\Models\ActivationKey;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class FederationController extends Controller
{

    /**
     * Retourne l'arborescence Ligues -> Clubs pour la fédération connectée.
     */
    public function mesLiguesEtClubs(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une fédération peut consulter cette vue.'
            ], 403);
        }

        $leagues =  League::where('federation_id', $activeId)
            ->with('clubs')
            ->get();

        // Statut d'affiliation (saison active) de chaque club, pour l'affichage
        // d'un badge "à jour / en vérification / non affilié" dans l'arborescence.
        $saisonActive = Saison::where('active', true)
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', 'Federation')
            ->first();

        $affiliation = $saisonActive
            ? Affiliation::where('federation_id', $activeId)->where('saison_id', $saisonActive->id)->first()
            : null;

        $statusByClub = $affiliation
            ? Transaction::where('payable_type', Transaction::PAYABLE_AFFILIATION)
                ->where('payable_id', $affiliation->id)
                ->pluck('status', 'club_id')
            : collect();

        $leagues->each(function ($league) use ($statusByClub) {
            $league->clubs->each(function ($club) use ($statusByClub) {
                $club->affiliation_status = $statusByClub->get($club->id);
            });
        });

        return response()->json([
            'success' => true,
            'data'    => $leagues,
        ]);
    }
    /**
     * Crée une nouvelle fédération en utilisant une clé d'activation valide.
     */
    public function store(Request $request)
    {
        $messages = [
            'name.required' => 'Le nom de la fédération est requis',
            'name.max' => 'Le nom de la fédération est trop long',
            'code.required' => 'Le code de la fédération est requis',
            'code.unique' => 'Le code de la fédération est déjà utilisé',
            'code.max' => 'Le code de la fédération est trop long',
            'country_id.required' => 'Le pays est requis',
            'country_id.exists' => 'Le pays n\'existe pas',
            'address.max' => 'L\'adresse est trop longue',
            'website.url' => 'L\'adresse du site est invalide',
            'logo.mimes' => 'Le logo doit être un fichier image',
            'logo.max' => 'Le logo est trop grand',
        ];
        $validated = $request->validate([
            // Étape 1 : Informations de la fédération
            'name'           => 'required|string|max:255',
            'code'           => 'required|unique:federations,code|string|max:10', // ex: FBK, FEBADA
            'country_id'     => 'required|exists:countries,id',
            'address'        => 'nullable|string',
            'website'        => 'nullable|url',
            'logo'           => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048', // 2 Mo max

            // Étape 2 : Sécurité / Clé d'activation
            'activation_key' => 'required|string',
            'mandate_end_at' => 'nullable|date|after:now',
        ], $messages);

        // =========================================================================
        // VERIFICATION DE LA CLÉ D'ACTIVATION
        // =========================================================================
        // On cherche une clé valide, non utilisée, et destinée à une Fédération
        $key = ActivationKey::where('key_code', $validated['activation_key'])
            ->where('is_used', false)
            ->where('type', 'federation') // Pour éviter qu'on utilise une clé de Club ici
            ->first();

        if (!$key) {
            return response()->json([
                'errors' => ['activation_key' => ["La clé d'activation est invalide ou déjà utilisée."]]
            ], 422);
        }

        $user = $request->user();

        // On utilise une transaction DB pour être sûr que si un truc plante, on ne crée rien
        DB::beginTransaction();

        try {
            // =========================================================================
            // STOCKAGE DU LOGO (si fourni)
            // =========================================================================
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('federations/logos', 'public');
            }

            // =========================================================================
            // CRÉATION DE LA FÉDÉRATION
            // =========================================================================
            $federation = Federation::create([
                'name'       => $validated['name'],
                'code'       => strtoupper($validated['code']),
                'country_id' => $validated['country_id'],
                'address'    => $validated['address'],
                'website'    => $validated['website'],
                'logo'       => $logoPath,
            ]);

            // =========================================================================
            // ATTACHEMENT DE L'UTILISATEUR (Comme Admin de la Fédération)
            // =========================================================================
            $adminRole = Role::where('name', 'admin')->first();

            $user->federations()->attach($federation->id, [
                'role_id'          => $adminRole->id,
                'mandate_start_at' => now(),
                'mandate_end_at'   => $validated['mandate_end_at'] ?? null,
                'mandate_status'   => 1,
            ]);
            // On marque la clé comme utilisée par cette fédération
            $key->update([
                'is_used' => true,
                'used_by_user_id' => $user->id,
                'used_by_organisation_id' => $federation->id,
                'used_at' => now(),
            ]);
            $user->updateQuietly([
                'current_federation_id' => $federation->id,
            ]);

            DB::commit();

            // =========================================================================
            // RECALCUL DU PACKAGE MULTI-TENANT POUR REACT
            // =========================================================================
            $user->load(['clubs', 'leagues', 'federations', 'federations.roles']);
            $allRoles = Role::all()->keyBy('id');

            // Un helper générique plus léger pour tes structures
            $formatOrg = function ($org) use ($allRoles) {
                $roleId = $org->pivot->role_id ?? null;
                $roleName = $roleId ? ($allRoles->get($roleId)->name ?? null) : null;

                return [
                    'id'              => $org->id,
                    'name'            => $org->name,
                    'code'            => $org->code ?? null,
                    'league_id'       => $org->league_id ?? null,
                    'federation_id'   => $org->federation_id ?? null,
                    'role'            => $roleName ? [$roleName] : [],
                    'type'            => class_basename($org)
                ];
            };

            return response()->json([
                'success'          => true,
                'message'          => "Fédération nationale créée avec succès !",
                'user'             => $user,
                'new_federation'   => [
                    'id'   => $federation->id,
                    'type' => 'Federation',
                    'role' => ['admin']
                ],
                'clubs'            => $user->clubs->map($formatOrg),
                'leagues'          => $user->leagues->map($formatOrg),
                'federations'      => $user->federations->map($formatOrg),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // Si la fédération n'a pas pu être créée mais que le logo a été stocké,
            // on supprime le fichier orphelin pour ne pas laisser de déchets sur le disque
            if (!empty($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }

            return response()->json([
                'message' => "Une erreur est survenue lors de la création de la fédération.",
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
