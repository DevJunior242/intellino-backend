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
            'country_id' => ['required', 'exists:countries,id'],
            'region' => ['bail', 'regex:/^[\pL\s\d\-\.,\#\/\(\)\']+$/u', 'max:50'],
            'address' => ['bail', 'nullable', 'regex:/^[\pL\s\d\-\.,\#\/\(\)\']+$/u', 'max:50'],
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:1024',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire',
            'name.unique' => 'Le nom existe déjà',
            'country_id.required' => 'Le pays est obligatoire',
            'country_id.exists' => 'Le pays n\'existe pas',
            'region.required' => 'La région est obligatoire',
            'region.max' => 'La région est trop longue',
            'region.regex' => 'La région contient des caractères non valides',
            'address.max' => 'L\'adresse est trop longue',
            'address.regex' => 'L\'adresse contient des caractères non valides',

            'logo.mimes' => 'Le logo doit être un fichier image',
            'logo.max' => 'Le logo doit être inférieur à 1Mo',
        ];
    }
}
