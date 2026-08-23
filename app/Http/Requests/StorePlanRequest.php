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
            'amount' => ['bail', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'organisateur_type' => ['bail', 'required', 'in:Club,Ligue,Federation'],
            'min_users' => ['bail', 'required', 'integer', 'min:0'],
            'max_users' => ['bail', 'nullable', 'integer', 'gt:min_users'],
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
            'amount.min' => 'Le montant ne peut pas être négatif',
            'amount.max' => 'Le montant est trop grand',
            'amount.numeric' => 'Le montant doit être un nombre',
            'organisateur_type.required' => 'Le type d\'organisation est requis',
            'organisateur_type.in' => 'Le type d\'organisation doit être Club, Ligue ou Federation',
            'min_users.required' => 'Le seuil minimum d\'utilisateurs est requis',
            'max_users.gt' => 'Le seuil maximum doit être supérieur au minimum',
        ];
    }
}




