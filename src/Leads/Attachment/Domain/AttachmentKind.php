<?php

namespace Reformas\Leads\Attachment\Domain;

enum AttachmentKind: string
{
    case Photo = 'photo';
    case Plan = 'plan';

    public static function fromValue(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidAttachmentKindError($value);
    }
}
