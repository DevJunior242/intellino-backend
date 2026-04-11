<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
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
            'attendances' => 'required|array',

             'attendances.*.student_id' => 'required|exists:students,id',
            'attendances.*.session_id' => 'required|exists:session_models,id',
            'attendances.*.status'     => 'required|in:present,absent',
        ];
    }
    public function messages(): array
    {
        return [
            '*.student_id.exists' => "L'élève sélectionné est invalide.",
            '*.session_id.exists' => "La session sélectionnée est invalide.",
        ];
    }
}
