<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClubStoreRequest extends FormRequest
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
            'country_id' => ['bail', 'required', 'exists:countries,id'],
            'city' => ['bail', 'regex:/^[\pL\s\d\-\.,\#\/\(\)\']+$/u', 'max:50'],
            'name' => ['bail', 'required', 'regex:/^[\pL\s\d\-]+$/u', 'max:50'],
            'logo' => ['bail', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'address' => ['bail', 'nullable', 'regex:/^[\pL\s\d\-\.,\#\/\(\)\']+$/u', 'max:50'],
            // Optionnelle : sans clé, le club démarre en essai (voir
            // ResolvesTrialStatus / ClubController::store).
            'activation_key' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'country_id.required' => 'Le pays est requis',
            'country_id.exists' => 'Le pays n\'existe pas',
            'name.required' => 'Le nom est requis',
            'name.max' => 'Le nom est trop long',
            'city.required' => 'La ville est requise',
            'city.max' => 'La ville est trop longue',
            'logo.mimes' => 'Le logo doit être un fichier image',
            'logo.max' => 'Le logo est trop grand',

            'address.max' => 'L\'adresse est trop longue',
        ];
    }
}
