<?php

namespace App\Http\Controllers;

use App\Models\Medal;
use Illuminate\Http\Request;
use App\Http\Requests\storeMedalRequest;

class MedalController extends Controller
{
    public function getMedals()
    {
        $medals = Medal::select(
            'id',
            'name',
        )
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(
            [
                'success' => true,
                'message' => 'medals retrieved successfully',
                'medals' => $medals
            ],
            200
        );
    }
    public function storeMedal(storeMedalRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        $clubId = $request->input('club_id');
        $authorizedRoles = ['admin', 'secretaire', 'instructeur'];
        $hasPermission = $user->clubs()
            ->where('club_id', $clubId)
            ->whereHas('roles', function ($q) use ($authorizedRoles) {
                $q->whereIn('name', $authorizedRoles);
            })
            ->exists();
        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas les droits pour accéder à cette page',
            ], 403);
        }
        $medal = Medal::create(
            [
                ...$validated,
                'club_id' => $clubId
            ]
        );
        return response()->json(
            [
                'success' => true,
                'message' => 'medal created successfully',
                'medal' => $medal
            ],
            201
        );
    }
}
