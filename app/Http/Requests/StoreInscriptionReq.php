<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInscriptionReq extends FormRequest
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
            'organisateur_id' => 'required|uuid',
            'organisateur_type' => 'required|string|in:Ligue,Club',
            'competition_id'      => 'required|exists:competitions,id',
            'athlete_id'          => 'required|exists:students,id',
            'poids_declare'       => 'nullable|numeric|min:0',
            'kata'                => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'competition_id.required' => 'Le champ competition est obligatoire.',
            'competition_id.exists' => 'Le champ competition est invalide.',
            'athlete_id.required' => 'Le champ athlete est obligatoire.',
            'athlete_id.exists' => 'Le champ athlete est invalide.',
            'poids_declare.required' => 'Le champ poids_declare est obligatoire.',
            'poids_declare.numeric' => 'Le champ poids_declare doit être un nombre.',
            'poids_declare.min' => 'Le champ poids_declare doit être supérieur à 0.',
            'kata.required' => 'Le champ kata est obligatoire.',
            'kata.max' => 'Le champ kata doit être de 255 caractères maximum.',
        ];
    }
}
