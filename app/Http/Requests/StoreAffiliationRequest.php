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
            'cotisation' => 'required|numeric|min:0',
            'date_affiliation' => 'required|date',
            'statut' => 'nullable|string|in:actif,expiré',
        ];
    }

    public function messages(): array
    {
        return [
            'saison.regex' => 'Le format de la saison doit être "Début-Fin" (ex: 2024-2025).',
            'saison.unique' => 'Ce club est déjà affilié pour cette saison.',
        ];
    }
}
