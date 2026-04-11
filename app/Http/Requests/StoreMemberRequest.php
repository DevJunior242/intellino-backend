<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
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
            //
            'fullname' => ['bail', 'required', 'regex:/^[\pL\s\d\-]+$/u', 'max:50'],
            'phone' => ['bail', 'required', 'regex:/^(\+226|00226)?[0567]\d{7}$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
            'profession' => [
                'bail',
                'nullable',
                'regex:/^[\pL\s\d\-]+$/u',
                'max:50'
            ],
            'domicile' => [
                'bail',
                'nullable',
                'regex:/^[\pL\s\d\-]+$/u',
                'max:100'
            ],
            'relation' => [
                'bail',
                'nullable',
                'regex:/^[\pL\s\d\-]+$/u',
                'max:50'
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'fullname.required' => 'Le nom complet est requis',
            'fullname.regex' => 'Le nom complet ne peut contenir que des lettres, des chiffres et des tirets',
            'fullname.max' => 'Le nom complet est trop long',
            'phone.required' => 'Le numéro de téléphone est requis',
            'phone.unique' => 'Le numéro de téléphone est déjà utilisé',
            'email.required' => 'L\'email est requis',
            'email.email' => 'L\'email est invalide',
            'email.unique' => 'L\'email est déjà utilisé',
            'role_id.required' => 'Le role est requis',
            'role_id.exists' => 'Le role existe pas',
            'profession.max' => 'La profession est trop longue',
            'domicile.max' => 'La domicile est trop longue',
            'relation.max' => 'La relation est trop longue',
        ];
    }
}
