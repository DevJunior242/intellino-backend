<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['clubs', 'leagues'])->select('id', 'fullname', 'email', 'phone')
            ->get();
        return response()->json($users);
    }
}
