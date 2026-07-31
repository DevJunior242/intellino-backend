<?php

namespace App\Http\Requests;

use App\Models\Competition;
use Illuminate\Foundation\Http\FormRequest;

class StoreKataTeamReq extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'competition_id'          => 'required|exists:competitions,id',
            'nom'                     => 'required|string|max:255',
            'membres'                 => 'required|array|min:3|max:4',
            'membres.*.student_id'    => 'required|distinct|exists:students,id',
            'membres.*.est_reserve'   => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'competition_id.required'       => 'Le champ compétition est obligatoire.',
            'competition_id.exists'         => 'Le champ compétition est invalide.',
            'nom.required'                  => 'Le nom de l\'équipe est obligatoire.',
            'membres.required'              => 'Veuillez sélectionner les membres de l\'équipe.',
            'membres.min'                   => 'Une équipe de Kata compte 3 à 4 athlètes (Art. 3.5 WKF).',
            'membres.max'                   => 'Une équipe de Kata compte 3 à 4 athlètes (Art. 3.5 WKF).',
            'membres.*.student_id.required' => 'Le champ élève est obligatoire.',
            'membres.*.student_id.exists'   => 'Le champ élève est invalide.',
            'membres.*.student_id.distinct' => 'Un élève ne peut être sélectionné qu\'une fois.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $competition = Competition::with('subDiscipline')->find($this->input('competition_id'));

            if (!$competition) {
                return;
            }

            $estKata = trim(strtolower($competition->subDiscipline?->nom ?? '')) === 'kata';

            if (!$estKata || !$competition->est_equipe) {
                $validator->errors()->add('competition_id', 'Cette épreuve n\'accepte pas les inscriptions par équipe.');
            }
        });
    }
}
