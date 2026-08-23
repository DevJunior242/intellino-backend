<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PlatformPaymentMethod;

/**
 * Moyens de paiement d'Intellino elle-même (comptes recevant les
 * abonnements Club/Ligue/Fédération) — distinct de PaymentMethodController,
 * qui gère les moyens de paiement d'une organisation pour se faire payer
 * par une autre (Club → Ligue, etc.), pas la plateforme elle-même.
 */
class PlatformPaymentMethodController extends Controller
{
    private const PROVIDERS = ['orange_money', 'moov_money', 'wave', 'virement_bancaire'];

    /**
     * Le super admin voit tout (gestion) ; toute autre organisation
     * connectée ne voit que les moyens actifs (pour déclarer un paiement).
     */
    public function index(Request $request)
    {
        $role = $request->attributes->get('role');
        $isSuperAdmin = $request->user()?->isSuperAdmin() || $role === 'super_admin';

        $methods = PlatformPaymentMethod::query()
            ->when(!$isSuperAdmin, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_active')
            ->orderBy('label')
            ->get();

        return response()->json(['success' => true, 'data' => $methods]);
    }

    private function assertSuperAdmin(Request $request)
    {
        if (!($request->user()?->isSuperAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Seul le super administrateur peut gérer les moyens de paiement de la plateforme.',
            ], 403);
        }
        return null;
    }

    public function store(Request $request)
    {
        if ($refus = $this->assertSuperAdmin($request)) return $refus;

        $validated = $request->validate([
            'provider'       => 'required|string|in:' . implode(',', self::PROVIDERS),
            'label'          => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_name'   => 'nullable|string|max:100',
        ]);

        $method = PlatformPaymentMethod::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Moyen de paiement ajouté.',
            'data'    => $method,
        ], 201);
    }

    public function update(Request $request, PlatformPaymentMethod $platformPaymentMethod)
    {
        if ($refus = $this->assertSuperAdmin($request)) return $refus;

        $validated = $request->validate([
            'provider'       => 'sometimes|required|string|in:' . implode(',', self::PROVIDERS),
            'label'          => 'sometimes|required|string|max:100',
            'account_number' => 'sometimes|required|string|max:50',
            'account_name'   => 'nullable|string|max:100',
        ]);

        $platformPaymentMethod->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Moyen de paiement mis à jour.',
            'data'    => $platformPaymentMethod,
        ]);
    }

    public function toggleActive(Request $request, PlatformPaymentMethod $platformPaymentMethod)
    {
        if ($refus = $this->assertSuperAdmin($request)) return $refus;

        $platformPaymentMethod->update(['is_active' => !$platformPaymentMethod->is_active]);

        return response()->json([
            'success' => true,
            'message' => $platformPaymentMethod->is_active ? 'Moyen de paiement activé.' : 'Moyen de paiement désactivé.',
            'data'    => $platformPaymentMethod,
        ]);
    }

    public function destroy(Request $request, PlatformPaymentMethod $platformPaymentMethod)
    {
        if ($refus = $this->assertSuperAdmin($request)) return $refus;

        $platformPaymentMethod->delete();

        return response()->json(['success' => true, 'message' => 'Moyen de paiement supprimé.']);
    }
}
