<?php

namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;
use Reformas\Leads\Attachment\Domain\AttachmentSize;

final class UploadAttachmentRequest extends FormRequest
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
        // El límite de tamaño también aquí: rechazar antes de leer el archivo entero en memoria.
        // El formato y el tipo los valida el dominio.
        return [
            'kind' => ['required', 'string'],
            'file' => ['required', 'file', 'max:'.(AttachmentSize::MAX_BYTES / 1024)],
        ];
    }
}
