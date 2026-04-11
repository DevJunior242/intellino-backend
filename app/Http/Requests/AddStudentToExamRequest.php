<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddStudentToExamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'uuid',
                // Vérifie que le student appartient au club de l'admin
                function ($attribute, $value, $fail) {
                    $exists = \App\Models\Student::where('id', $value)
                        ->where('club_id', $this->user()->current_club_id)
                        ->exists();
                    if (! $exists) {
                        $fail('Ce student n’appartient pas à votre club.');
                    }
                },
            ],
            'examen_league_id' => [
                'required',
                'uuid',
                'exists:examen_leagues,id',
            ],
        ];
    }
}
