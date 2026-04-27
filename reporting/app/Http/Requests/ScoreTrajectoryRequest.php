<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScoreTrajectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tool_id' => ['nullable', 'integer', 'exists:tools,id'],
            'group_id' => ['nullable', 'string'],
        ];
    }
}
