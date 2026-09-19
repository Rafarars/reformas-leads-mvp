<?php

namespace Reformas\Leads\Attachment\Domain;

// Lista blanca: lo que no está aquí no entra. Con una lista negra siempre falta algo.
final readonly class AttachmentContentType
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (! array_key_exists($normalized, self::EXTENSIONS)) {
            throw new InvalidAttachmentContentTypeError($value);
        }

        $this->value = $normalized;
    }

    public function extension(): string
    {
        return self::EXTENSIONS[$this->value];
    }

    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        return array_keys(self::EXTENSIONS);
    }
}
