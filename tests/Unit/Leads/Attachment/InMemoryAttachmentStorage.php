<?php

namespace Tests\Unit\Leads\Attachment;

use Reformas\Leads\Attachment\Domain\AttachmentStorage;

final class InMemoryAttachmentStorage implements AttachmentStorage
{
    /** @var array<string, string> */
    public array $files = [];

    public function write(string $path, string $contents): void
    {
        $this->files[$path] = $contents;
    }

    public function read(string $path): ?string
    {
        return $this->files[$path] ?? null;
    }

    public function delete(string $path): void
    {
        unset($this->files[$path]);
    }
}
