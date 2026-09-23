<?php

namespace App\Http\Requests\Api\V1\Telegram;

use Illuminate\Foundation\Http\FormRequest;

final class LinkTelegramRequest extends FormRequest
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
        // Solo presencia y tipos básicos. El formato del token y del chat_id lo valida el dominio.
        return [
            'token' => ['required', 'string'],
            'chat_id' => ['required'],
        ];
    }
}
