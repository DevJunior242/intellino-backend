<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\SessionModel;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\CourseRequest;

class CourseController extends Controller
{
    public function index(Request $request)
    {



        $sessions = SessionModel::with(['course.club', 'course.grade'])
            ->whereHas('course', function ($query) use ($request) {
                $query->where('club_id', $request->validated_club_id);
            })
            ->latest()
            ->paginate(6);
        return response()->json([
            'success' => true,
            'message' => 'listes des sessions ',
            'sessions' => $sessions,
        ]);
    }
    public function storeFullCourse(CourseRequest $request)
    {
        $validated = $request->validated();
        return DB::transaction(function () use ($validated, $request) {
            $course = Course::create([
                ...$validated['course'],
                'club_id' => $request->validated_club_id,
                'instructor_id' => $request->user()->id,
            ]);
            $session = collect($validated['sessions'])->map(function ($session) {
                return [
                    'title' => $session['title'],
                    'session_date' => $session['session_date'],
                    'start_time' => $session['start_time'],
                    'end_time' => $session['end_time'],
                    'description' => $session['description'] ?? null,
                ];
            });
            $course->sessions()->createMany($session->toArray());

            return response()->json(['success' => true, 'message' => 'Cours créé avec ses sessions', 'data' => $course->load('sessions')], 201);
        });
    }

    public function show(SessionModel $session)
    {

        $session->load([
            'course.club',
            'course.grade',
            'course.instructor',
        ]);
        return response()->json([
            'success' => true,
            'message' => 'liste des sessions ',
            'session' => $session,
        ]);
    }
}
