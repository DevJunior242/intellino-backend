<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class storeTournRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required',  'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required',  'date_format:H:i, after:start_time'],
        ];
    }
    public function messages(): array
    {
        return [
            'club_id.required' => 'Le club est requis',
            'name.required' => 'Le nom est requis',
            'name.max' => 'Le nom est trop long',
            'location.required' => 'Le lieu est requis',
            'location.max' => 'Le lieu est trop long',
            'start_date.required' => 'La date de début est requise',
            'start_date.date' => 'La date de début est invalide',
            'end_date.required' => 'La date de fin est requise',
            'end_date.date' => 'La date de fin est invalide',
            'start_time.required' => 'L\'heure de début est requise',
            'start_time.date_format' => 'L\'heure de début est invalide',
            'end_time.required' => 'L\'heure de fin est requise',
            'end_time.date_format' => 'L\'heure de fin est invalide',
            'end_time.after' => 'L\'heure de fin doit être après l\'heure de début',
        ];
    }
}
