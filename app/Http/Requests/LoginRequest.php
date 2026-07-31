<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            // Pas de règle de longueur/complexité ici : la connexion doit
            // laisser Auth::attempt() juger (et renvoyer "Identifiants
            // invalides"), pas rejeter la requête avant même d'essayer. Ces
            // règles n'ont leur place qu'à l'inscription (RegisterRequest).
            'password' => ['required', 'string'],
            // 'captcha_token' => ['required', new \App\Rules\Turnstile],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L\'email est requis',
            'email.email' => 'L\'email est invalide',
            'email.max' => 'L\'email est trop long',
            'password.required' => 'Le mot de passe est requis',
        ];
    }
}
