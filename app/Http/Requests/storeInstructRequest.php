<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class storeInstructRequest extends FormRequest
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
            'phone' => ['bail',  'regex:/^(\+226|00226)?[0567]\d{7}$/', 'unique:instructors,phone'],
            'grade' => ['bail', 'nullable', 'regex:/^[\pL\s\d\-]+$/u', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'club_id.required' => 'Le club est requis',
            'fullname.required' => 'Le nom complet est requis',
            'fullname.max' => 'Le nom complet est trop long',
            'phone.required' => 'Le numéro de téléphone est requis',
            'phone.unique' => 'Le numéro de téléphone est déjà utilisé',
            'grade.max' => 'La relation est trop longue',
        ];
    }
}
