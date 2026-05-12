<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Saison;
use App\Models\SessionModel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\CourseRequest;

class CourseController extends Controller
{
    public function index(Request $request)
    {

        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $saisonActive =  Saison::where('active', true)->first();

        if (!$saisonActive) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez définir une saison pour créer un cours',
            ], 422);
        }
        $saisonActive =  Saison::where('active', true)->first();

        $sessions = SessionModel::with(['course.organisateur', 'course.grade'])
            ->whereHas('course', function ($query) use ($activeId, $saisonActive, $activeType) {
                $query->where('organisateur_id', $activeId)
                    ->where('organisateur_type', $activeType)
                    ->where('saison_id', $saisonActive->id);
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
        $saisonActive =  Saison::where('active', true)->first();
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$saisonActive) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez définir une saison pour créer un cours',
            ], 422);
        }

        $validated = $request->validated();
        return DB::transaction(function () use ($validated, $request, $activeId, $activeType, $saisonActive) {
            $course = Course::create([
                ...$validated['course'],
                'instructor_id' => $request->user()->id,
                'saison_id' => $saisonActive->id,
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
            'course.organisateur',
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
