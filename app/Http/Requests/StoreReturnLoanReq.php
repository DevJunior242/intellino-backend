<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnLoanReq extends FormRequest
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
            'quantity_returned' => ['required', 'integer', 'min:0'],
            'quantity_lost' => ['required', 'integer', 'min:0'],
            'quantity_damaged' => ['required', 'integer', 'min:0'],
            'club_id' => ['required', 'uuid'],
        ];
    }
}
