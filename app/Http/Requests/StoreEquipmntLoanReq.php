<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmntLoanReq extends FormRequest
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
            'quantity_loaned' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:internal,external'],

            'to_club_id' => [
                'required_if:type,external',
                'exists:clubs,id',
                'different:club_id'
            ],
            'status' => ['sometimes', 'in:active,returned,lost,damaged,partiale'],
            'quantity_returned' => ['sometimes', 'integer', 'min:0'],
            'quantity_lost' => ['sometimes', 'integer', 'min:0'],
            'quantity_damaged' => ['sometimes', 'integer', 'min:0'],
            'beneficiary' => [
                'required',
                'string',
                'max:255'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'to_club_id.different' => 'Ce club sélectionné est identique .',
            'quantity_loaned.required' => 'La quantité à prêter est requise.',
            'quantity_loaned.integer' => 'La quantité à prêter doit être un nombre entier.',
            'quantity_loaned.min' => 'La quantité à prêter doit être supérieure à 0.',
            'quantity_returned.integer' => 'La quantité retournée doit être un nombre entier.',
            'quantity_returned.min' => 'La quantité retournée doit être supérieure à 0.',
            'quantity_lost.integer' => 'La quantité perdue doit être un nombre entier.',
            'quantity_lost.min' => 'La quantité perdue doit être supérieure à 0.',
            'quantity_damaged.integer' => 'La quantité endommagée doit être un nombre entier.',
            'quantity_damaged.min' => 'La quantité endommagée doit être supérieure à 0.',
            'type.required' => 'Le type de prêt est requis.',
            'type.in' => 'Le type de prêt doit être "internal" ou "external".',
            'beneficiary.required' => 'Le bénéficiaire est requis.',

        ];
    }
}
