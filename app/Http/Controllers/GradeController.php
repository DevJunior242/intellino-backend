<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\storeGradeReq;
use Illuminate\Database\QueryException;

class GradeController extends Controller
{

    public function index(Request $request)
    {

        $user = auth()->user();

        $clubId = $request->validated_club_id;

        $grades = Grade::where('club_id', $clubId)->get();

        return response()->json([
            'message' => 'La liste des grades',
            'grades' => $grades,
        ], 200);
    }
    public function store(storeGradeReq $request)
    {
        try {

            $validated = $request->validated();
             $clubId = $request->validated_club_id;
            Log::info('club_id', ['clubId' => $clubId]);

            $grade = \App\Models\Grade::create([
                ...$validated,
                'club_id' => $clubId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Grade crée avec succé',
                'grade' => $grade,
            ], 201);
        } catch (QueryException $e) {
            $errcode = $e->getCode();
            $errmessage = $e->getMessage();
            Log::error('erreur', ['code' => $errcode, 'message' => $errmessage]);
            //erreur 23000
            if ($errcode == '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'cet grade existe déjà',
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la note',
            ], 500);
        }
    }
}
