<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationReq extends FormRequest
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

            'commentaire' =>  ['nullable', 'string', 'max:255'],
            'notes' =>  ['required',  'array'],
            'notes.*.enchainement_id' =>  ['required', 'uuid', 'exists:grade_enchainements,id'],
            'notes.*.score' =>  ['required', 'decimal:0,1'],

        ];
    }

    public function messages(): array
    {
        return [
            'commentaire.string' => 'Le champ commentaire doit être une chaine de caractère',
            'notes.*.enchainement_id.required' => 'Le champ enchainement_id est obligatoire.',
            'notes.*.score' => ['required', 'numeric', 'between:0,20'],
        ];
    }
}
