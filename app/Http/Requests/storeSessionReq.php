<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class storeSessionReq extends FormRequest
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
            'course_id' => ['required', 'exists:courses,id'],
            'session_date' => ['bail', 'required', 'date'],
            'start_time' => ['required', 'date_format:H:i' ],
            'end_time' => ['required',  'date_format:H:i, after:start_time'],
         ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'Le club est requis',
            'session_date.required' => 'La date est requise',
            'session_date.date' => 'La date est invalide',
            'start_time.required' => 'L\'heure de début est requise',
            'start_time.date_format' => 'L\'heure de début est invalide',
            'end_time.required' => 'L\'heure de fin est requise',
            'end_time.date_format' => 'L\'heure de fin est invalide',
            'end_time.after' => 'L\'heure de fin doit être après l\'heure de début',
        ];
    }
}
