<?php

namespace App\Models;

use App\Models\Kata;
use Illuminate\Database\Eloquent\Model;

class KataController extends Model
{
    public function index()
    {
        $katas = Kata::select('id', 'nom', 'niveau')->get();
        return response()->json($katas);
    }
}
