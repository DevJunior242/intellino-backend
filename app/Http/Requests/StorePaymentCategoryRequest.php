<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentCategoryRequest extends FormRequest
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
            'name' => 'required|string|max:100',
            'slug' => 'required|string|unique:payment_categories,slug',
            'affects_validity' => 'required|boolean'
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Le nom est obligatoire',
            'name.max' => 'Le nom doit faire moins de 100 caractères',
            'slug.required' => 'Le slug est obligatoire',
            'slug.unique' => 'Le slug doit être unique',
            'affects_validity.required' => 'Le champ "Affecte la validité" est obligatoire',
        ];
    }
}
