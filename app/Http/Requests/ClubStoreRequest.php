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
            'discipline_id' => ['bail', 'required', 'exists:disciplines,id'],
            'name' => ['bail', 'required', 'regex:/^[\pL\s\d\-]+$/u', 'max:50'],
            'logo' => ['bail', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'country' => ['bail', 'required', 'regex:/^[\pL\s\d\-]+$/u', 'max:50'],
            'city' => ['bail', 'required', 'regex:/^[\pL\s\d\-]+$/u', 'max:50'],
            'address' => ['bail', 'nullable', 'regex:/^[\pL\s\d\-]+$/u', 'max:50'],
            'phone' => ['bail', 'required', 'regex:/^(\+226|00226)?[0567]\d{7}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'discipline_id.required' => 'Le discipline est requis',
            'discipline_id.exists' => 'Le discipline n\'existe pas',
            'name.required' => 'Le nom est requis',
            'name.max' => 'Le nom est trop long',
            'logo.mimes' => 'Le logo doit être un fichier image',
            'logo.max' => 'Le logo est trop grand',
            'country.required' => 'Le pays est requis',
            'country.max' => 'Le pays est trop long',
            'city.required' => 'La ville est requis',
            'city.max' => 'La ville est trop longue',
            'address.max' => 'L\'adresse est trop longue',
            'phone.required' => 'Le numéro de téléphone est requis',
        ];
    }
}
