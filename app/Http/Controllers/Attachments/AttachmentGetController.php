<?php

namespace App\Http\Controllers\Attachments;

use Reformas\Leads\Attachment\Application\Download\AttachmentReader;
use Reformas\Leads\Attachment\Application\Download\AttachmentReaderRequest;
use Symfony\Component\HttpFoundation\Response;

// Los archivos no se sirven desde el disco público: pasan por aquí, detrás de la sesión.
final class AttachmentGetController
{
    public function __invoke(string $attachment, AttachmentReader $read): Response
    {
        $file = $read(new AttachmentReaderRequest($attachment));

        return new Response($file->contents, 200, [
            'Content-Type' => $file->contentType,
            'Content-Disposition' => 'inline; filename="'.addslashes($file->fileName).'"',
        ]);
    }
}
