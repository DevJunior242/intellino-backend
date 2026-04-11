<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
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
            'student_id' => 'required|exists:students,id',
            'pricing_plan_id' => 'required|exists:pricing_plans,id',
            'amount_paid' => 'required|numeric|min:100',
            'payment_method' => 'required|string|in:cash,orange_money,moov_money,check,transfer',
            'payment_reference' => 'nullable|string|max:100',
            'starts_at' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.exists' => "L'élève sélectionné n'existe pas.",
            'pricing_plan_id.exists' => "Le plan tarifaire sélectionné est invalide.",
            'amount_paid.required' => "Le montant versé est obligatoire.",
            'payment_method.in' => "La méthode de paiement choisie n'est pas reconnue.",
            'starts_at.date' => "La date de début doit être une date valide.",
        ];
    }
}
