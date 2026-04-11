<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class storeMatchReq extends FormRequest
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
            'student_id' => ['required', 'exists:students,id'],
            'tournament_id' => ['required', 'exists:tournaments,id'],
            'category' => ['required', 'string', 'max:255'],
            'round' => ['required', 'string', 'max:255'],
            'opponent' => ['required', 'string', 'max:255'],
            'result' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Le student est requis',
            'tournament_id.required' => 'Le tournoi est requis',
            'category.required' => 'La catégorie est requise',
            'category.max' => 'La catégorie est trop longue',
            'round.required' => 'Le round est requis',
            'round.max' => 'Le round est trop long',
            'opponent.required' => 'L\'adversaire est requis',
            'opponent.max' => 'L\'adversaire est trop long',
            'result.required' => 'Le résultat est requis',
            'result.max' => 'Le résultat est trop long',
        ];
    }
}
