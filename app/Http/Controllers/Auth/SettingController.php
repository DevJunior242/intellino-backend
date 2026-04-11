<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    //photo
    public function uploadPhoto(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }
        $file = $request->file('photo');
        if ($file) {
            $ext = $file->getClientOriginalExtension();
            $fileName = uniqid() . '.' . $ext;
            $user->photo = $file->storeAs('user', $fileName, 'public');
        }
        $user->save();
        return response()->json([
            'success' => true,
            'message' => 'Photo mise à jour avec succès',
            'photo' => $user->photo ? url('storage/' . $user->photo) : null,
            'user' => $user,
        ]);
    }
    //update user profile 

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $user->fullname = $request->input('fullname');
        $user->email = $request->input('email');
        $user->phone = $request->input('phone');
        $user->save();
        return response()->json([
            'success' => true,
            'message' => 'votre profil a été mis à jour avec succès',
            'user' => $user
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'votre mot de passe a été mis à jour avec succès',
        ]);
    }

    //delete user
    public function deleteAccount(Request $request)
    {
        $user = auth()->user();
        $user->delete();
        return response()->json([
            'success' => true,
            'message' => 'votre compte a été supprimé avec succès',
        ]);
    }
}
