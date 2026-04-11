<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCandidatRequest extends FormRequest
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
            'examen_id' => [
                'required',
                'uuid',
                'exists:examens,id',
            ],
            'student_id' => [
                'required',
                'uuid',
                'exists:students,id',
            ],
            'status' => [
                'nullable',
                Rule::in(['registered', 'absent', 'evaluated']),
            ],
        ];
    }

     
     public function messages(): array
    {
        return [
            'examen_id.required' => 'Le champ examen est obligatoire.',
            'examen_id.uuid' => 'Le champ examen doit être un identifiant valide.',
            'examen_id.exists' => 'Le examen spécifié n\'existe pas.',

            'student_id.required' => 'Le champ étudiant est obligatoire.',
            'student_id.uuid' => 'Le champ étudiant doit être un UUID valide.',
            'student_id.exists' => 'L\'étudiant spécifié n\'existe pas.',

             'status.in' => 'Le statut doit être absent, evaluated ou registered.',
        ];
    }
}
