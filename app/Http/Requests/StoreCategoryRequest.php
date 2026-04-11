<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
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
            'nom' => 'required|string|max:100|unique:categories,nom',
            'age_min' => 'required|integer|min:3|max:99',
            'age_max' => 'required|integer|min:3|max:99|gte:age_min', // gte = Greater Than or Equal
            'disciplines' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'age_max.gte' => 'L\'âge maximum ne peut pas être inférieur à l\'âge minimum.',
            'nom.unique' => 'Cette catégorie existe déjà.',
        ];
    }
}
