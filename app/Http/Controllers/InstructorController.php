<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use Illuminate\Http\Request;
use App\Http\Requests\storeInstructRequest;

class InstructorController extends Controller
{


    public function getInstructor()
    {
        $instructors = Instructor::all();
        return response()->json([
            'success' => true,
            'message' => 'Instructors retrieved successfully',
            'instructors' => $instructors,
        ]);
    }
    public function storeInstructor(storeInstructRequest $request)
    {
        $user = auth()->user();
        if (!$user || !$user->club_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to access this page',
            ], 403);
        }
        $instructor = Instructor::create(
            [
                'club_id' => $user->club_id,
                ...$request->validated(),
            ]
        );
        return response()->json([
            'success' => true,
            'message' => 'Instructor added successfully',
            'instructor' => $instructor,
        ]);
    }
}
