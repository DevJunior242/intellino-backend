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
            'cotisation' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'cotisation.required' => 'Vous devez indiquer le montant de la cotisation.',
            'cotisation.min' => 'La cotisation doit être supérieure à 0.',
        ];
    }
}
