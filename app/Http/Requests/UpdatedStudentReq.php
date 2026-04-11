<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatedStudentReq extends FormRequest
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
            'fullname' => [
                'bail',
                'nullable',
                'regex:/^[\pL\s\d\-]+$/u',
                'max:50'
            ],
            'birthdate' => ['nullable', 'date'],
            'sex' => ['required', Rule::in(['M', 'F'])],
            'photo' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpeg,png,jpg',
                'max:2048'
            ],
            'status' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            //en francais 
            'fullname.regex' => 'Le nom complet ne peut contenir que des lettres, des chiffres et des tirets',
            'fullname.max' => 'Le nom complet est trop long',
            'birthdate.date' => 'La date est invalide',
            'sex.in' => 'Le sexe est invalide',
            'photo.image' => 'Le fichier photo est invalide',
            'photo.mimes' => 'Le fichier photo doit être un fichier image',
            'photo.max' => 'Le fichier photo est trop gros',


        ];
    }
}
