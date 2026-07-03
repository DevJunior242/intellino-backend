<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'phone' => [
                'bail',
                'required',
                'regex:/^(\+226|00226)?[05678]\d{7}$/',
                'unique:users,phone'
            ],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'fullname.required' => 'Le nom complet est requis',
            'fullname.max' => 'Le nom complet est trop long',
            'phone.required' => 'Le numéro de téléphone est requis',
            'phone.unique' => 'Le numéro de téléphone est déjà utilisé',
            'phone.regex' => 'Le numéro de téléphone est invalide',
            'email.required' => 'L\'email est requis',
            'email.email' => 'L\'email est invalide',
            'email.unique' => 'L\'email est déjà utilisé',
            'email.max' => 'L\'email est trop long',
            'password.required' => 'Le mot de passe est requis',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
            'password.confirmed' => 'Les mots de passe ne correspondent pas',
        ];
    }
}
