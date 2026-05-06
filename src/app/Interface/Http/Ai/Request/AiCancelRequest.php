<?php

declare(strict_types=1);

namespace App\Interface\Http\Ai\Request;

use Illuminate\Foundation\Http\FormRequest;

class AiCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'session_id.required' => 'Session ID не указан',
        ];
    }
}
