<?php

namespace App\Http\Controllers;

use App\Models\KumiteFormat;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class KumiteFormatController extends Controller
{
    public function index()
    {
        $kumiteFormats = KumiteFormat::orderBy('ordre')->get();
        return response()->json($kumiteFormats);
    }
}
