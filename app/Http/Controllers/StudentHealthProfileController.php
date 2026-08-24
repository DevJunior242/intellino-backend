<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use App\Models\StudentHealthProfile;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Fiche santé d'un élève — purement informative (rien n'est bloqué par son
 * absence ou son expiration), généralement remplie dès l'inscription (voir
 * StudentController::store) mais modifiable ensuite ici (renouvellement du
 * certificat médical, mise à jour des allergies, etc.).
 */
class StudentHealthProfileController extends Controller
{
    use AuthorizesRequests;

    public function show(Request $request, Student $student)
    {
        $this->authorize('update', $student);

        return response()->json([
            'success' => true,
            'data' => $student->healthProfile,
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $this->authorize('update', $student);

        $validated = $request->validate([
            'groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'conditions_medicales' => ['nullable', 'string', 'max:1000'],
            'medecin_nom' => ['nullable', 'string', 'max:255'],
            'medecin_telephone' => ['nullable', 'string', 'max:50'],
            'contact_urgence_nom' => ['nullable', 'string', 'max:255'],
            'contact_urgence_telephone' => ['nullable', 'string', 'max:50'],
            'contact_urgence_relation' => ['nullable', 'string', 'max:100'],
            'certificat_medical_fourni' => ['nullable', 'boolean'],
            'certificat_medical_expire_le' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $profile = StudentHealthProfile::updateOrCreate(
            ['student_id' => $student->id],
            $validated + ['updated_by' => $request->user()->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Fiche santé mise à jour.',
            'data' => $profile,
        ]);
    }
}
