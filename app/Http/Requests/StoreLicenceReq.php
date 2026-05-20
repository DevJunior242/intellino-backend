<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLicenceReq extends FormRequest
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
            'club_id' => 'required|uuid|exists:clubs,id',
            'student_id' => [
                'required',
                'uuid',
                'exists:students,id',
                // Unicité combinée : l'élève ne peut avoir qu'une licence par saison
                Rule::unique('licences')->where(function ($query) {
                    return $query->where('student_id', $this->student_id)
                        ->where('saison_id', $this->saison_id);
                }),
            ],
            // 'saison' => 'required|string|regex:/^\d{4,10}-\d{4,10}$/',
            'type' => 'required|string',
            'grade_au_moment' => 'nullable|string|max:50',
            'montant' => 'nullable|numeric|min:0',
            'date_emission' => 'required|date',
            'date_expiration' => 'required|date|after:date_emission',
        ];
    }
    public function messages(): array
    {
        return [
            'student_id.unique' => 'Cet élève possède déjà une licence pour la saison ' . $this->saison,
            'date_expiration.after' => 'La date d\'expiration doit être après la date d\'émission.',
        ];
    }
}
