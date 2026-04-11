<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
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
            'name' => ['bail', 'required','unique:plans,name', 'max:255'],
            'description' => ['bail', 'nullable', 'string', 'max:255'],
            'amount' => ['bail', 'required', 'numeric', 'min:1000', 'max:99999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est requis',
            'name.max' => 'Le nom est trop long',
            'name.unique' => 'Le nom existe déjà',
            'description.max' => 'La description est trop longue',
            'amount.required' => 'Le montant est requis',
            'amount.min' => 'Le montant doit être supérieur à 1000',
            'amount.max' => 'Le montant est trop grand',
            'amount.numeric' => 'Le montant doit être un nombre',
        ];
    }
}




