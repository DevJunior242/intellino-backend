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
    public function rules()
    {
        return [
            'club_id' => ['required', 'exists:clubs,id'],
            'is_own_responsible' => ['required', 'boolean'],

            // Validation du Parent (si applicable)
            'parent_fullname' => ['required_if:is_own_responsible,false', 'nullable', 'string'],
            'parent_email'    => ['required_if:is_own_responsible,false', 'nullable', 'email'],
            'parent_phone'    => ['required_if:is_own_responsible,false', 'nullable', 'string', 'regex:/^(\+226|00226)?[0567]\d{7}$/'],
            // Validation du TABLEAU d'élèves
            'students' => ['required', 'array', 'min:1'],

            // On valide chaque élément à l'intérieur du tableau students
            'students.*.fullname' => ['bail', 'required', 'regex:/^[\pL\s\d\-]+$/u', 'max:50'],
            'students.*.birthdate' => ['required', 'date', 'before_or_equal:today'],
            'students.*.sex' => ['required', 'string', 'max:255'],

            // Validation conditionnelle pour l'email et le téléphone
            // Si is_own_responsible est TRUE, on force l'email/phone au sein de l'objet student
            'students.*.email' => [
                'bail',
                'nullable',
                'required_if:is_own_responsible,true',
                'email',
                'unique:users,email'
            ],
            'students.*.phone' => [
                'bail',
                'nullable',
                'required_if:is_own_responsible,true',
                'string',
                'regex:/^(\+226|00226)?[0567]\d{7}$/',
                'unique:users,phone'
            ],
        ];
    }

    public function messages()
    {
        return [
            'students.*.fullname.required' => 'Le nom complet de l\'élève est requis',
            'students.*.email.required_if' => 'L\'email est obligatoire pour créer le compte du responsable',
            'students.*.email.unique' => 'Cet email est déjà utilisé',
            'students.*.phone.unique' => 'Ce numéro de téléphone est déjà utilisé',
            'students.*.phone.regex' => 'Le numéro de téléphone est invalide',
            'students.*.is_own_responsible.boolean' => 'Le champ est_own_responsible doit être un booléen',
            'students.*.create_account.boolean' => 'Le champ create_account doit être un booléen',
            'students.*.birthdate.date' => 'La date est invalide',
            'students.*.birthdate.before_or_equal' => 'La date doit être antérieure à aujourd\'hui',
            'students.*.sex.in' => 'Le sexe est invalide',
            'students.*.photo.image' => 'Le fichier photo est invalide',
            'students.*.photo.mimes' => 'Le fichier photo doit être un fichier image',
            'students.*.photo.max' => 'Le fichier photo est trop gros',
            'parent_fullname.required_if' => 'Le nom du parent est obligatoire',
            'parent_email.required_if' => 'L\'email du parent est obligatoire',
            'parent_email.email' => 'L\'email du parent doit être une adresse email valide',
            'parent_phone.required_if' => 'Le téléphone du parent est obligatoire',
            'parent_phone.regex' => 'Le numéro de téléphone du parent est invalide',
            'club_id.exists' => 'Le club n\'existe pas',

        ];
    }
}
