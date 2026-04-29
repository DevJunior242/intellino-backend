<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAffiliationRequest extends FormRequest
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
            'club_id' => 'required|uuid|exists:clubs,id',
            // Regex : autorise "2024-2025"  
            'saison' => [
                'required',
                'string',
                'regex:/^\d{4,10}-\d{4,10}$/',
                'unique:affiliations,saison,NULL,id,club_id,' . $this->club_id
            ],
            'cotisation' => 'nullable|numeric|min:0',
            'date_debut' => 'required|date|before_or_equal:date_fin',
            'date_fin' => 'required|date|after:date_debut',
        ];
    }

    public function messages(): array
    {
        return [
            'saison.regex' => 'Le format de la saison doit être "Début-Fin" (ex: 2024-2025).',
            'saison.unique' => 'Ce club est déjà affilié pour cette saison.',
            'date_debut.after' => 'La date de début doit être postérieure à la date de fin.',
            'date_fin.before' => 'La date de fin doit être antérieure à la date de début.',
            'cotisation.min' => 'La cotisation doit être supérieure à 0.',
        ];
    }
}
