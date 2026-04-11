<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePricingPlanRequest extends FormRequest
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
            'club_id' => 'required|exists:clubs,id',
            'payment_category_id' => 'required|exists:payment_categories,id',
            'label' => 'required|string|max:150',
            'price' => 'required|numeric|min:0',
            'duration_value' => 'nullable|integer|min:0',
            'duration_unit' => 'nullable|in:day,month,year',
        ];
    }
}
