<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CourseRequest extends FormRequest
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
            'course.organisateur_id' => 'required|uuid',
            'course.organisateur_type' => 'required|string|in:Ligue,Club',
            'course.current_grade_id' => ['bail', 'required', 'exists:grades,id'],
            'course.name' => ['bail', 'required', 'regex:/^[\pL\s\d\-,.\']+$/u', 'max:50'],
            'sessions' => ['bail', 'required', 'array', 'min:1'],
            'sessions.*.title' => ['bail', 'required', 'max:50'],
            'sessions.*.description' => ['bail', 'nullable', 'max:500'],
            'sessions.*.session_date' => ['bail', 'required', 'date'],
            'sessions.*.start_time' => ['required', 'date_format:H:i'],
            'sessions.*.end_time' => [
                'required',
                'date_format:H:i',
                'after:sessions.*.start_time'

            ],
            'sessions.*.replacement_instructor_id' => ['nullable', 'exists:users,id'],
            'sessions.*.parent_session_id' => ['nullable', 'exists:session_models,id'],


        ];
    }

    public function messages(): array
    {
        return [
            'course.current_grade_id.exists' => 'Le cours est invalide',
            'course.current_grade_id.required' => 'Le cours est requis',
            'course.name.required' => 'Le nom complet est requis',
            'course.name.max' => 'Le nom complet est trop long',
            'course.name.regex' => 'Le nom complet ne peut contenir que des lettres, des chiffres et des tirets',
            'course.level.max' => 'La relation est trop longue',

            'instructor_id.required' => 'L\'instructeur est requis',
            'instructor_id.exists' => 'L\'instructeur est invalide',
            'sessions.*.session_date.required' => 'La date est requise',
            'sessions.*.session_date.date' => 'La date est invalide',
            'sessions.*.start_time.required' => 'L\'heure de début est requise',
            'sessions.*.start_time.date_format' => 'L\'heure de début est invalide',
            'sessions.*.end_time.required' => 'L\'heure de fin est requise',
            'sessions.*.end_time.date_format' => 'L\'heure de fin est invalide',
            'sessions.*.end_time.after' => 'L\'heure de fin doit être après l\'heure de début',
            'sessions.*.replacement_instructor_id.exists' => 'L\'instructeur est invalide',
            'sessions.*.parent_session_id.exists' => 'La session parent est invalide',
            'sessions.*.title.required' => 'Le titre est requis',
            'sessions.*.title.max' => 'Le titre est trop long',
            'sessions.*.description.max' => 'La description est trop longue',
        ];
    }
}
