<?php

namespace Reformas\Leads\Attachment\Application\Download;

use Reformas\Leads\Attachment\Domain\AttachmentId;
use Reformas\Leads\Attachment\Domain\AttachmentNotFoundError;
use Reformas\Leads\Attachment\Domain\AttachmentRepository;
use Reformas\Leads\Attachment\Domain\AttachmentStorage;

final readonly class AttachmentReader
{
    public function __construct(
        private AttachmentRepository $attachments,
        private AttachmentStorage $storage,
    ) {}

    public function __invoke(AttachmentReaderRequest $request): AttachmentReaderResponse
    {
        $attachment = $this->attachments->search(new AttachmentId($request->attachmentId))
            ?? throw new AttachmentNotFoundError($request->attachmentId);

        // Si la fila existe pero el archivo no, es un dato roto, no un adjunto que "no existe".
        $contents = $this->storage->read($attachment->path())
            ?? throw new AttachmentNotFoundError($request->attachmentId);

        return new AttachmentReaderResponse($contents, $attachment->fileName()->value, $attachment->contentType()->value);
    }
}
