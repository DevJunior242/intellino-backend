<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\Session;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\ParentModel;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Http\Requests\StoreAttendanceRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AttendanceController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $request->attributes->get('role');
        $activeId = $request->attributes->get('organisateur_id');

        $query = Attendance::with([
            'student.club:id,name,logo',
            'session.course:id,name',
        ]);

        if ($role !== 'super_admin') {
            $query->where('club_id', $activeId);
        }

        if ($role === 'parent') {
            $parent = \App\Models\ParentModel::where('user_id', $user->id)->first();

            if (!$parent) {
                return response()->json(['message' => 'Profil parent non trouvé'], 404);
            }

            $studentIds = $parent->students()->pluck('students.id');

            $query->whereIn('student_id', $studentIds);
        }
        //student presence
        if ($role === 'etudiant') {
            $student = Student::where('user_id', $user->id)->first();
            if (!$student) {
                return response()->json(['message' => 'Vous n\'etes pas inscrit à ce club'], 403);
            }
            $query->where('student_id', $student->id);
        }

        $attendances = $query->latest()->paginate(6);

        return response()->json([
            'success' => true,
            'message' => match ($role) {
                'parent' => 'Liste des présences de vos enfants',
                'super_admin' => 'Liste globale des présences (tous clubs)',
                default => 'Liste des présences du club',
            },
            'attendances' => $attendances
        ]);
    }
    public function show($id)
    {
        $attendance = Attendance::with(['student', 'session'])->findOrFail($id);
        return response()->json($attendance);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('update', Attendance::class);
        $attendance = Attendance::findOrFail($id);
        $validated = $request->validate([
            'attendance' => 'required|in:present,absent',
        ]);

        $attendance->update($validated);
        return response()->json($attendance);
    }

    public function destroy($id)
    {
        $this->authorize('delete', Attendance::class);
        $attendance = Attendance::findOrFail($id);
        $attendance->delete();
        return response()->json(['message' => 'Attendance deleted']);
    }


    public function bulkStore(StoreAttendanceRequest $request)
    {
        // Récupération sécurisée des données
        // $this->authorize('create', Attendance::class);
        $activeId = $request->attributes->get('organisateur_id');
        $validatedData = $request->validated();
        $attendances = $validatedData['attendances'];
        $saisonActive =  Saison::where('active', true)->first();
        if (!$saisonActive) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez définir une saison pour créer des présences',
            ], 422);
        }

        // Début de la transaction sécurisée
        DB::beginTransaction();

        try {
            foreach ($attendances as $data) {
                Attendance::updateOrCreate(
                    [
                        'club_id'    => $activeId,
                        'student_id' => $data['student_id'],
                        'session_id' => $data['session_id'],
                        'saison_id' => $saisonActive->id,
                    ],
                    [
                        'status'     => $data['status'],
                    ]
                );
            }

            // Si tout s'est bien passé, on valide en base de données
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'La liste de présence a été mise à jour avec succès.'
            ], 200);
        } catch (\Exception $e) {
            // En cas d'erreur, on annule tout ce qui a été fait dans le foreach
            DB::rollBack();


            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'enregistrement.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function getStudentAttendance(Request $request)
    {
        $saisonActive =  Saison::where('active', true)->firsts();
        $activeId = $request->attributes->get('organisateur_id');
        $attendances = Attendance::with(['session.course:id,name', 'student:id,fullname'])
            ->where('club_id', $activeId)
            ->where('saison_id', $saisonActive->id)
            ->where('status', 'absent')
            ->latest()
            ->take(5)
            ->get();

        return response()->json($attendances);
    }
}
