<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->unreadNotifications;

        return response()->json($notifications);
    }

    public function markAsRead(Request $request)
    {
        $user = auth()->user();
        $ids = $request->input('ids');

        if (empty($ids)) {
            return response()->json(['message' => 'Aucun ID fourni'], 400);
        }

        $user->unreadNotifications()
            ->whereIn('id', (array) $ids)
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications mises à jour'
        ]);
    }
}
