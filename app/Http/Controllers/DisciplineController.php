<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use Illuminate\Http\Request;

class DisciplineController extends Controller
{
    public function index()
    {
        //
        $disciplines = Discipline::select('id', 'name')->get();
        return response()->json($disciplines);
    }

    public function store(Request $request)
    {
        //

        $request->validate([
            'name' => 'required|string|unique:disciplines,name',
            'description' => 'nullable|string',
        ]);
        $data = [
            'name' => $request->name,
            'description' => $request->description,
        ];
        $discipline = Discipline::create($data);
        return response()->json(
            [
                'success' => true,
                'message' => 'votre discipline a ete cree avec succes',
                'discipline' => $discipline,
            ]
        );
    }
}
