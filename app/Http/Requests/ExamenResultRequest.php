<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExamenResultRequest extends FormRequest
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
            'total_score' => 'required|integer|min:0',
            'decision' => 'nullable|in:pass,fail',
        ];
    }
    public function messages(): array
    {
        return [
            'total_score.required' => 'Le champ total_score est obligatoire.',
            'total_score.integer' => 'Le champ total_score doit être un entier.',
            'decision.nullable' => 'Le champ decision est obligatoire.',
            'decision.in' => 'Le champ decision doit être pass ou fail.',
        ];
    }
}
