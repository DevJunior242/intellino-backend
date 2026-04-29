<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamenRequest extends FormRequest
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
            'organisateur_id' => 'required|uuid',
            'organisateur_type' => 'required|string|in:Ligue,Club',
            'current_grade_id' => [
                'required',
                'uuid',
                'exists:grades,id',
            ],
            'next_grade_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) {
                    $nextGrade = \App\Models\Grade::find($value);
                    $type = $this->input('organisateur_type');

                    if ($nextGrade && str_contains(strtolower($nextGrade->name), 'noire')) {
                        if ($type !== 'Ligue') {
                            $fail("Seules les Ligues peuvent organiser des passages de grade pour la ceinture noire.");
                        }
                    }
                },
            ],

            'start_date' => [
                'required',
                'date',
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date',
            ],

            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required',  'date_format:H:i, after:start_time'],
            'status' => [
                'nullable',
                Rule::in(['brouillon', 'en cours', 'terminé']),
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

            'date.required' => 'La date est obligatoire.',
            'date.date' => 'Le champ date doit être une date valide.',
            'date.after' => 'La date doit être après la date de début.',

            'status.required' => 'Le statut est obligatoire.',
            'status.in' => 'Le statut doit être draft, ongoing ou validated.',

            'created_by.required' => 'Le champ créateur est obligatoire.',
            'created_by.uuid' => 'Le champ créateur doit être un UUID valide.',
            'created_by.exists' => 'L\'utilisateur créateur n\'existe pas.',


        ];
    }
}
