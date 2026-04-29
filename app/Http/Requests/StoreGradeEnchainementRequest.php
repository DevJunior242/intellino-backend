<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGradeEnchainementRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation pour les champs.
     */
    public function rules(): array
    {
        return [
            // 'organisateur_id' => 'required|uuid',
            // 'organisateur_type' => 'required|string|in:Ligue,Club',

            'name' => [
                'required',
                'string',
                Rule::unique('grade_enchainements')
                    ->where('examen_id', $this->examen_id),
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'diviseur' => [
                'required',
                'integer',
            ],

        ];
    }

    /**
     * Messages d'erreur personnalisés.
     */
    public function messages(): array
    {
        return [
            'grade_id.required' => 'Le champ grade est obligatoire.',
            'grade_id.uuid' => 'Le champ grade doit être un UUID valide.',
            'grade_id.exists' => 'Le grade spécifié n\'existe pas.',

            'name.required' => 'Le nom est obligatoire.',
            'name.string' => 'Le nom doit être une chaîne de caractères.',
            'name.max' => 'Le nom ne doit pas dépasser 255 caractères.',

            'description.string' => 'La description doit être une chaîne de caractères.',

            'order.integer' => 'L\'ordre doit être un nombre entier.',
        ];
    }
}
