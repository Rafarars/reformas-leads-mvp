<?php

namespace Reformas\Leads\Attachment\Domain;

// El almacén no participa en la transacción de la base: por eso quien lo usa tiene que limpiar
// explícitamente lo que escribió si algo falla después.
interface AttachmentStorage
{
    public function write(string $path, string $contents): void;

    public function read(string $path): ?string;

    public function delete(string $path): void;
}
