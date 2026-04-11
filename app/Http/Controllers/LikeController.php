<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function store(Club $club)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être connecté pour ajouter un like',
            ], 401);
        }
        $like = $club->likes()->where('user_id', $user->id)->first();
        if ($like) {
            $like->delete();
        } else {
            $club->likes()->create(['user_id' => $user->id]);
        }
        return back();
    }
}
