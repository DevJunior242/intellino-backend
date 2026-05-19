<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ActivationKey;

class ActivationKeyController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:league,club',
            'comment' => 'nullable|string|max:255'
        ]);

        ActivationKey::where('is_used', false)
            ->update([
                'is_used' => true,
                'used_at' => now(),
            ]);
        // Génère un format pro : LIGUE-2026-ABCD-1234
        $prefix = $request->type === 'league' ? 'LIGUE' : 'CLUB';
        $annee = now()->year;
        $random = strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));

        $keyCode = "{$prefix}-{$annee}-{$random}";

        $key = ActivationKey::create([
            'key_code' => $keyCode,
            'type' => $request->type,
            'comment' => $request->comment,
            'is_used' => false
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Clé générée avec succès',
            'data' => $key
        ]);
    }
}
