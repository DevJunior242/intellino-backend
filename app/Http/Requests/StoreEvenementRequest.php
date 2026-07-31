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


            // Validation du tableau des catégories
            'epreuves' => 'required|array|min:1',
            'epreuves.*.category_id' => 'required|uuid|exists:categories,id',
            'epreuves.*.sub_discipline_id' => 'required|uuid|exists:sub_disciplines,id',
            'epreuves.*.niveau_id' => 'required|uuid|exists:niveaux_competitions,id',
            'epreuves.*.est_equipe' => 'nullable|boolean',
            'epreuves.*.heure_debut_prevu' => [
                'required',
                'date',
            ],
            'epreuves.*.heure_fin_prevue' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    preg_match('/epreuves\.(\d+)\.heure_fin_prevue/', $attribute, $matches);
                    $index = $matches[1] ?? null;

                    if ($index === null) {
                        return;
                    }

                    $debut = $this->input("epreuves.$index.heure_debut_prevu");

                    if ($debut && strtotime($value) <= strtotime($debut)) {
                        $fail("L'heure de fin de l'épreuve doit être après l'heure de début.");
                    }
                },
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
            'epreuves.*.sub_discipline_id.exists' => 'Le disciplineleague sélectionnée n\'existe pas.',
            'epreuves.*.niveau_id.exists' => 'Le niveau sélectionné n\'existe pas.',
            'epreuves.*.heure_debut_prevu.date' => 'La date de début doit être une date.',
            'epreuves.*.heure_fin_prevue.date' => 'La date de fin doit être une date.',
        ];
    }
}
