<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StudentGradeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'current_grade_id' => ['required', 'exists:grades,id'],
            'awarded_at' => ['required', 'date'],
        ];
    }

    public function messages()
    {
        return [
            'student_id.required' => 'Le champ étudiant est obligatoire.',
            'student_id.exists' => 'L\'étudiant sélectionné n\'existe pas.',
            'current_grade_id.required' => 'Leniveau de grade est obligatoire.',
            'current_grade_id.exists' => 'Le niveau de grade  sélectionnée n\'existe pas.',
            'awarded_at.required' => 'La date d\'attribution est obligatoire.',
            'awarded_at.date' => 'La date d\'attribution doit être une date valide.',
        ];
    }
}
