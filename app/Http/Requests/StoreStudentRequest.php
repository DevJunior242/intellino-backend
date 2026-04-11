<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
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
            'fullname' => ['bail', 'required', 'regex:/^[\pL\s\d\-]+$/u', 'max:50'],
            'birthdate' =>  ['required', 'date', 'before_or_equal:today'],
            'sex' =>  ['required', 'string', 'max:255'],
            'photo' => ['bail', 'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'status' => ['nullable', 'string', 'max:255'],
            'is_own_responsible' => ['required', 'boolean'],
            'user_id' => ['required_if:is_own_responsible,false', 'nullable', 'exists:users,id'],
            'email' => ['bail', 'nullable', 'required_if:is_own_responsible,true', 'email', 'unique:users,email'],
            'phone' => ['bail', 'nullable', 'required_if:is_own_responsible,true', 'string', 'regex:/^(\+226|00226)?[0567]\d{7}$/', 'unique:users,phone'],
        ];
    }

    public function messages(): array
    {
        return [
            'fullname.required' => 'Le nom complet est requis',
            'fullname.regex' => 'Le nom complet ne peut contenir que des lettres, des chiffres et des tirets',
            'fullname.max' => 'Le nom complet est trop long',
            'birthdate.date' => 'La date est invalide',
            'birthdate.before_or_equal' => 'La date doit être antérieure à aujourd\'hui',
            'sex.required' => 'Le sexe est requis',
            'sex.in' => 'Le sexe est invalide',
            'photo.image' => 'Le fichier photo est invalide',
            'photo.mimes' => 'Le fichier photo doit être un fichier image',
            'photo.max' => 'Le fichier photo est trop gros',
            'parent_id.exists' => 'Le parent existe pas',
            'email.email' => 'L\'adresse email est invalide',
            'email.unique' => 'Cet email est déjà utilisé',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé',
            'phone.regex' => 'Le numéro de téléphone est invalide',
            'is_own_responsible.boolean' => 'Le champ est_own_responsible doit être un booléen',




        ];
    }
}
