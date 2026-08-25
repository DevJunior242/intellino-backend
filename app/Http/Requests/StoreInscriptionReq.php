<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
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
            'competition_id'                => 'required|exists:competitions,id',
            'inscriptions'                  => 'required|array|min:1',
            'inscriptions.*.athlete_id'      => 'required|distinct|exists:students,id',
            'inscriptions.*.poids_declare'   => 'nullable|numeric|min:0',
            // Kata WKF Art. 5.1 : un athlète doit présenter un kata précis du
            // catalogue officiel — laisser ce champ vide produit un passage
            // sans nom de kata affiché aux juges/écran public. Nullable pour
            // le Kumite uniquement (aucun kata n'y a de sens).
            'inscriptions.*.kata_id'         => [
                $this->competitionEstKata() ? 'required' : 'nullable',
                Rule::exists('katas', 'id')->where('actif', true),
            ],
        ];
    }

    private function competitionEstKata(): bool
    {
        $competition = \App\Models\Competition::with('subDiscipline')->find($this->input('competition_id'));

        return strtolower($competition?->subDiscipline?->nom ?? '') === 'kata';
    }

    public function messages(): array
    {
        return [
            'competition_id.required' => 'Le champ competition est obligatoire.',
            'competition_id.exists' => 'Le champ competition est invalide.',
            'inscriptions.required' => 'Veuillez sélectionner au moins un athlète.',
            'inscriptions.min' => 'Veuillez sélectionner au moins un athlète.',
            'inscriptions.*.athlete_id.required' => 'Le champ athlete est obligatoire.',
            'inscriptions.*.athlete_id.exists' => 'Le champ athlete est invalide.',
            'inscriptions.*.athlete_id.distinct' => 'Un athlète ne peut être sélectionné qu\'une fois.',
            'inscriptions.*.poids_declare.numeric' => 'Le champ poids_declare doit être un nombre.',
            'inscriptions.*.poids_declare.min' => 'Le champ poids_declare doit être supérieur à 0.',
            'inscriptions.*.kata_id.exists' => 'Le kata sélectionné est invalide.',
            'inscriptions.*.kata_id.required' => 'Veuillez saisir votre kata.',
        ];
    }

    /**
     * Rejette les poids déclarés hors des bornes de la catégorie (Kumite).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $competition = \App\Models\Competition::with(['category', 'subDiscipline'])->find($this->input('competition_id'));
            $category = $competition?->category;

            // Kata WKF Art. 5.1 : un athlète doit présenter un kata précis du
            // catalogue officiel — laisser kata_id vide produit un passage
            // sans nom de kata affiché aux juges/écran public.
            $estKata = strtolower($competition?->subDiscipline?->nom ?? '') === 'kata';
            if ($estKata) {
                foreach ($this->input('inscriptions', []) as $index => $inscription) {
                    if (empty($inscription['kata_id'] ?? null)) {
                        $validator->errors()->add(
                            "inscriptions.{$index}.kata_id",
                            "Le kata est obligatoire pour une épreuve Kata."
                        );
                    }
                }
            }

            if (!$category || (is_null($category->poids_min) && is_null($category->poids_max))) {
                return;
            }

            foreach ($this->input('inscriptions', []) as $index => $inscription) {
                $poids = $inscription['poids_declare'] ?? null;

                if (!is_null($poids) && !$category->isPoidsValide((float) $poids)) {
                    $validator->errors()->add(
                        "inscriptions.{$index}.poids_declare",
                        "Le poids déclaré ({$poids}kg) est hors de la catégorie {$category->nom} ({$category->poids_label})."
                    );
                }
            }
        });
    }
}
