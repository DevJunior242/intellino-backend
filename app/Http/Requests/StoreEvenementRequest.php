<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvenementRequest extends FormRequest
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
            'nom' => 'required|string|max:255',
            'lieu' => 'required|string|max:255',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'organisateur_id' => 'required|uuid',
            'organisateur_type' => 'required|string|in:Ligue,Federation',

            // Validation du tableau des catégories
            'epreuves' => 'required|array|min:1',
            'epreuves.*.category_id' => 'required|uuid|exists:categories,id',
            'epreuves.*.disciplineleague_id' => 'required|uuid|exists:disciplineleagues,id',
            'epreuves.*.niveau_id' => 'required|uuid|exists:niveaux_competitions,id',
            'epreuves.*.heure_debut_prevu' => [
                'required',
                'date',
            ],
            'epreuves.*.heure_fin_prevue' => [
                'required',
                'date',
                'after:epreuves.*.heure_debut_prevu'
            ],

        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de l\'événement est obligatoire.',
            'lieu.required' => 'Le lieu de l\'événement est obligatoire.',
            'date_debut.required' => 'La date de début de l\'événement est obligatoire.',
            'date_fin.required' => 'La date de fin de l\'événement est obligatoire.',
            'organisateur_id.required' => 'L\'id de l\'organisateur est obligatoire.',
            'organisateur_type.required' => 'Le type de l\'organisateur est obligatoire.',
            'organisateur_type.in' => 'Le type de l\'organisateur doit être Ligue ou Federation.',
            'epreuves.*.category_id.exists' => 'La catégorie sélectionnée n\'existe pas.',
            'epreuves.*.disciplineleague_id.exists' => 'Le disciplineleague sélectionnée n\'existe pas.',
            'epreuves.*.niveau_id.exists' => 'Le niveau sélectionné n\'existe pas.',
        ];
    }
}
