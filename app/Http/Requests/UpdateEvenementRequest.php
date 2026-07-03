<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEvenementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // L'événement en cours d'édition (résolu par route-model-binding dans le contrôleur)
        $evenement = $this->route('evenement');

        return [
            'nom'        => 'required|string|max:255',
            'lieu'       => 'required|string|max:255',
            'date_debut' => 'required|date',
            'date_fin'   => 'required|date|after_or_equal:date_debut',

            'epreuves'   => 'required|array|min:1',

            // null/absent = nouvelle épreuve à créer.
            // Si présent, doit exister ET appartenir à CET événement
            // (empêche de réassigner l'id d'une épreuve d'un autre événement).
            'epreuves.*.id' => [
                'nullable',
                'uuid',
                Rule::exists('competitions', 'id')
                    ->where('evenement_id', $evenement?->id),
            ],

            'epreuves.*.category_id'         => 'required|uuid|exists:categories,id',
            'epreuves.*.sub_discipline_id'   => 'required|uuid|exists:sub_disciplines,id',
            'epreuves.*.niveau_id'           => 'required|uuid|exists:niveaux_competitions,id',

            'epreuves.*.heure_debut_prevu' => ['required', 'date'],

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
            'epreuves.*.id.exists' => "Une des épreuves envoyées n'appartient pas à cet événement.",
            'nom.required' => 'Le nom de l\'événement est obligatoire.',
            'lieu.required' => 'Le lieu de l\'événement est obligatoire.',
            'date_debut.required' => 'La date de début de l\'événement est obligatoire.',
            'date_fin.required' => 'La date de fin de l\'événement est obligatoire.',
            'epreuves.*.category_id.exists' => 'La catégorie sélectionnée n\'existe pas.',
            'epreuves.*.sub_discipline_id.exists' => 'Le disciplineleague sélectionnée n\'existe pas.',
            'epreuves.*.niveau_id.exists' => 'Le niveau sélectionné n\'existe pas.',
        ];
    }
}
