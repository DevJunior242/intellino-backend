<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAffiliationRequest extends FormRequest
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
            'club_id' => 'required|uuid|exists:clubs,id',
            'cotisation' => 'nullable|numeric|min:0',

        ];
    }

    public function messages(): array
    {
        return [
            'club_id.required' => 'Vous devez sélectionner un club.',
            'club_id.exists' => 'Le club sélectionné n\'existe pas.',
            'cotisation.min' => 'La cotisation doit être supérieure à 0.',
        ];
    }
}
