<?php

namespace Reformas\Leads\Attachment\Infrastructure\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Reformas\Leads\Attachment\Domain\AttachmentStorage;

// Disco privado: los archivos no se sirven solos desde el navegador; hay que pasar por la aplicación,
// que es quien comprueba la sesión.
final readonly class LaravelAttachmentStorage implements AttachmentStorage
{
    public function __construct(private Filesystem $disk) {}

    public function write(string $path, string $contents): void
    {
        $this->disk->put($path, $contents);
    }

    public function read(string $path): ?string
    {
        return $this->disk->exists($path) ? (string) $this->disk->get($path) : null;
    }

    public function delete(string $path): void
    {
        $this->disk->delete($path);
    }
}
