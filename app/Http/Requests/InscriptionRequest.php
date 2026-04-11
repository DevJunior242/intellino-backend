<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Models\Disciplineleague;
use Illuminate\Foundation\Http\FormRequest;

class InscriptionRequest extends FormRequest
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
        $discipline = Disciplineleague::find($this->disciplineleague_id);
        $isKumite = $discipline && strtolower($discipline->nom) === 'kumite';
        $isKata = $discipline && strtolower($discipline->nom) === 'kata';
        return [
            'competition_id' => 'required|exists:competitions,id',
            'athlete_id'     => 'required|exists:students,id',
            //le poid est requis pour kumite, mais pas pour kata
            'poids_declare'  => [
                $isKumite ? 'required' : 'nullable',
                'numeric',
                'between:5,150'
            ],
            'poids_officiel' => 'nullable|numeric|between:10,200',
            'kata_id'             => [
                $isKata ? 'required' : 'nullable',
                'exists:katas,id'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'poids_declare.required' => 'Le poids est obligatoire pour l\'inscription.',
            'athlete_id.exists'      => 'L\'athlète sélectionné est introuvable.',
        ];
    }
}
