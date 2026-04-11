<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompetitionRequest extends FormRequest
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
            'niveau_id' => 'required|exists:niveaux_competitions,id',
            'category_id' => 'required|exists:categories,id',
            'disciplineleague_id' => 'required|exists:disciplineleagues,id',
            'date_debut' => 'required|date|after_or_equal:today',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'lieu' => 'required|string',
            'organisateur_id' => 'required|uuid',
            'organisateur_type' => 'required|string|in:Ligue,Federation',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.',
            'nom.max' => 'Le nom est trop long.',
            'niveau_id.required' => 'Le niveau est obligatoire.',
            'niveau_id.exists' => 'Le niveau sélectionné n\'existe pas.',
            'category_id.required' => 'La catégorie est obligatoire.',
            'category_id.exists' => 'La catégorie sélectionnée n\'existe pas.',
            'date_debut.required' => 'La date de début est obligatoire.',
            'date_debut.after_or_equal' => 'La date de début doit être postérieure à aujourd\'hui.',
            'date_fin.required' => 'La date de fin est obligatoire.',
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure à la date de début.',
            'date_debut.date_format' => 'La date de début doit être au format jj-mm-aaaa hh:mm:ss.',
            'date_fin.date_format' => 'La date de fin doit être au format jj-mm-aaaa hh:mm:ss.',
            'lieu.required' => 'Le lieu est obligatoire.',
        ];
    }
}
