<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentMethod;

class PaymentMethodController extends Controller
{
    /**
     * Fournisseurs reconnus — sert à valider l'entrée et à garder
     * une liste cohérente d'options affichées côté front (select).
     */
    private const PROVIDERS = ['orange_money', 'moov_money', 'wave', 'virement_bancaire'];

    /**
     * Liste des moyens de paiement de l'organisateur connecté
     * (Federation, Ligue ou Club), y compris ceux désactivés —
     * la gestion se fait depuis cet écran.
     */
    public function index(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier l\'organisateur connecté.'
            ], 403);
        }

        $methods = PaymentMethod::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->orderByDesc('is_active')
            ->orderBy('label')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $methods,
        ]);
    }

    /**
     * Création d'un moyen de paiement pour l'organisateur connecté.
     */
    public function store(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier l\'organisateur connecté.'
            ], 403);
        }

        $validated = $request->validate([
            'provider'       => 'required|string|in:' . implode(',', self::PROVIDERS),
            'label'          => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_name'   => 'nullable|string|max:100',
            'is_active'      => 'sometimes|boolean',
        ]);

        $method = PaymentMethod::create([
            'organisateur_id'   => $activeId,
            'organisateur_type' => $activeType,
            'provider'          => $validated['provider'],
            'label'             => $validated['label'],
            'account_number'    => $validated['account_number'],
            'account_name'      => $validated['account_name'] ?? null,
            'is_active'         => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Moyen de paiement ajouté.',
            'data'    => $method,
        ], 201);
    }

    /**
     * Mise à jour d'un moyen de paiement — uniquement le sien.
     */
    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($paymentMethod->organisateur_id !== $activeId || $paymentMethod->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        $validated = $request->validate([
            'provider'       => 'sometimes|required|string|in:' . implode(',', self::PROVIDERS),
            'label'          => 'sometimes|required|string|max:100',
            'account_number' => 'sometimes|required|string|max:50',
            'account_name'   => 'nullable|string|max:100',
            'is_active'      => 'sometimes|boolean',
        ]);

        $paymentMethod->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Moyen de paiement mis à jour.',
            'data'    => $paymentMethod,
        ]);
    }

    /**
     * Active/désactive rapidement un moyen de paiement, sans repasser
     * par tout le formulaire — pratique pour un simple toggle visuel.
     */
    public function toggleActive(Request $request, PaymentMethod $paymentMethod)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($paymentMethod->organisateur_id !== $activeId || $paymentMethod->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        $paymentMethod->update(['is_active' => !$paymentMethod->is_active]);

        return response()->json([
            'success' => true,
            'message' => $paymentMethod->is_active ? 'Moyen de paiement activé.' : 'Moyen de paiement désactivé.',
            'data'    => $paymentMethod,
        ]);
    }

    /**
     * Suppression (soft delete) d'un moyen de paiement — uniquement le sien.
     */
    public function destroy(Request $request, PaymentMethod $paymentMethod)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($paymentMethod->organisateur_id !== $activeId || $paymentMethod->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        $paymentMethod->delete();

        return response()->json([
            'success' => true,
            'message' => 'Moyen de paiement supprimé.'
        ]);
    }

    /**
     * Consultation PUBLIQUE (côté payeur) des moyens de paiement actifs
     * d'un organisateur précis, identifié par son type + son id.
     *
     * Utilisé au moment de rediriger un club vers le paiement d'un stage
     * (organisateur = la Ligue qui organise) ou d'une licence
     * (organisateur = la Fédération qui facture).
     *
     * Pas de vérification d'identité ici au-delà de l'authentification
     * générale — n'importe quel organisateur connecté doit pouvoir voir
     * comment payer un autre organisateur, ce n'est pas une donnée privée.
     */
    public function pourOrganisateur(Request $request, string $type, string $id)
    {
        $typesAutorises = ['Federation', 'Ligue', 'Club'];

        if (!in_array($type, $typesAutorises, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Type d\'organisateur invalide.'
            ], 422);
        }

        $methods = PaymentMethod::where('organisateur_id', $id)
            ->where('organisateur_type', $type)
            ->where('is_active', true)
            ->orderBy('label')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $methods,
        ]);
    }
}
