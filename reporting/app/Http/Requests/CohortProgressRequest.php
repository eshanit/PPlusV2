<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CohortProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tool_id' => ['nullable', 'integer', 'exists:tools,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
        ];
    }
}
