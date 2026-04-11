<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResultRequest extends FormRequest
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
            'tournament_id' => ['required', 'string', 'exists:tournaments,id'],
            'student_id' => ['required', 'string', 'exists:students,id'],
            'medal_id' => ['required', 'string', 'exists:medals,id'],
            'score' => ['required', 'string'],
        ];
    }
}
