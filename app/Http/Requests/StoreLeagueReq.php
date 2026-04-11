<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeagueReq extends FormRequest
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
            'name' =>  ['required', 'string', 'unique:leagues,name'],
            'phone' => ['bail', 'required', 'regex:/^(\+226|00226)?[0567]\d{7}$/', 'unique:leagues,phone'],
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:1024',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire',
            'name.unique' => 'Le nom existe déjà',
            'phone.required' => 'Le numéro de téléphone est requis',
            'phone.unique' => 'Le numéro de téléphone est déjà utilisé',

            'logo.mimes' => 'Le logo doit être un fichier image',
            'logo.max' => 'Le logo doit être inférieur à 1Mo',
        ];
    }
}
