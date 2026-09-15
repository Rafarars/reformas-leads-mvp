<?php

namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;

final class ReceiveLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        // Solo presencia y tipos. El formato (teléfono, email, tipo de reforma) lo valida el dominio.
        return [
            'name' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'email' => ['required', 'string'],
            'renovation_type' => ['required', 'string'],
            'zone' => ['nullable', 'string'],
            'budget' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
        ];
    }
}
