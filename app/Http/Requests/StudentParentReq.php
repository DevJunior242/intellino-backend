<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentParentReq extends FormRequest
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
            //student 
            'student.fullname' => [
                'bail',
                'required',
                'regex:/^[\pL\s\d\-]+$/u',
                'max:50'
            ],
            'student.birthdate' => ['required', 'date'],
            'student.sex' => ['required', 'string', 'max:255'],
            'student.photo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg',
                'max:2048'
            ],


            //user_id
            'parent.user_id' => [
                'bail',
                'required',
                'exists:users,id'
            ],
            'parent.profession' => [
                'bail',
                'nullable',
                'regex:/^[\pL\s\d\-]+$/u',
                'max:50'
            ],
            'parent.domicile' => [
                'bail',
                'nullable',
                'regex:/^[\pL\s\d\-]+$/u',
                'max:100'
            ],
            'parent.relation' => [
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
            //en francais 
            'student.fullname.required' => 'Le nom complet est requis',
            'student.fullname.regex' => 'Le nom complet ne peut contenir que des lettres, des chiffres et des tirets',
            'student.fullname.max' => 'Le nom complet est trop long',
            'student.phone.required' => 'Le numéro de téléphone est requis',
            'student.phone.unique' => 'Le numéro de téléphone est déjà utilisé',
            'parent.fullname.required' => 'Le nom complet est requis',
            'parent.fullname.regex' => 'Le nom complet ne peut contenir que des lettres, des chiffres et des tirets',
            'parent.fullname.max' => 'Le nom complet est trop long',
            'parent.phone.required' => 'Le numéro de téléphone est requis',
            'parent.phone.unique' => 'Le numéro de téléphone est déjà utilisé',
            'parent.relation.max' => 'La relation est trop longue',

        ];
    }
}
