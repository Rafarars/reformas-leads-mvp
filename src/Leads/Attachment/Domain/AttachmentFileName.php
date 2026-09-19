<?php

namespace Reformas\Leads\Attachment\Domain;

// El nombre original solo se guarda para mostrarlo y descargarlo: nunca decide dónde se escribe el
// archivo, o un nombre con "../" saldría del directorio.
final readonly class AttachmentFileName
{
    private const MAX_LENGTH = 200;

    public string $value;

    public function __construct(string $value)
    {
        $normalized = trim((string) preg_replace('/[\/\\\\\x00-\x1F]+/', ' ', $value));
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $normalized));

        if ($normalized === '' || mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidAttachmentFileNameError($value);
        }

        $this->value = $normalized;
    }
}
