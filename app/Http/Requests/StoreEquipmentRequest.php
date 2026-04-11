<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentRequest extends FormRequest
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
            'club_id' => ['required', 'uuid', 'exists:clubs,id'],
            'equipment_category_id' => ['required', 'uuid', 'exists:equipment_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'total_quantity' => ['required', 'integer', 'min:1'],
             'min_stock_alert' => ['nullable', 'integer', 'min:2'],
        ];
    }
    public function messages(): array
    {
        return [
            'club_id.required' => 'Le club est obligatoire.',
            'club_id.exists' => 'Le club sélectionné est invalide.',
            'equipment_category_id.required' => 'La catégorie est obligatoire.',
            'equipment_category_id.exists' => 'La catégorie est invalide.',
             'total_quantity.min' => 'La quantité totale doit être supérieure à 1.',
        ];
    }
}
