<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamenLeagueReq extends FormRequest
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
            'title' => ['bail', 'required', 'string', 'max:255'],
            'grade' => ['bail', 'required', 'string', 'max:255'],
            'description' => ['bail', 'nullable', 'string', 'max:255'],
            'start_date' => ['bail', 'required', 'date'],
            'end_date' => ['bail', 'required', 'date:after,start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'grade.required' => 'Le grade est requis',
            'grade.max' => 'Le grade est trop long',
            'description.max' => 'La description est trop longue',
            'start_date.required' => 'La date de début est requise',
            'end_date.required' => 'La date de fin est requise',
            'end_date.after' => 'La date de fin doit être après la date de début',
        ];
    }
}
