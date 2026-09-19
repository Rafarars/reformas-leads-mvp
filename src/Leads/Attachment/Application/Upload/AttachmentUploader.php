<?php

namespace Reformas\Leads\Attachment\Application\Upload;

use Reformas\Leads\Attachment\Domain\AttachmentContentType;
use Reformas\Leads\Attachment\Domain\AttachmentFileName;
use Reformas\Leads\Attachment\Domain\AttachmentId;
use Reformas\Leads\Attachment\Domain\AttachmentKind;
use Reformas\Leads\Attachment\Domain\AttachmentRepository;
use Reformas\Leads\Attachment\Domain\AttachmentSize;
use Reformas\Leads\Attachment\Domain\AttachmentStorage;
use Reformas\Leads\Attachment\Domain\LeadAttachment;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadNotFoundError;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Reformas\Shared\Domain\Clock;
use Reformas\Shared\Domain\Transaction;
use Reformas\Shared\Domain\UuidGenerator;
use Throwable;

final readonly class AttachmentUploader
{
    public function __construct(
        private AttachmentRepository $attachments,
        private AttachmentStorage $storage,
        private LeadRepository $leads,
        private Transaction $transaction,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
    ) {}

    public function __invoke(AttachmentUploaderRequest $request): AttachmentUploaderResponse
    {
        // Se valida todo antes de tocar el disco: un archivo inválido no llega a escribirse.
        $leadId = new LeadId($request->leadId);
        $attachment = LeadAttachment::upload(
            new AttachmentId($this->uuidGenerator->generate()),
            $leadId,
            AttachmentKind::fromValue($request->kind),
            new AttachmentFileName($request->fileName),
            new AttachmentContentType($request->contentType),
            new AttachmentSize($request->sizeBytes),
            $this->clock->now(),
        );

        if ($this->leads->search($leadId) === null) {
            throw new LeadNotFoundError($request->leadId);
        }

        $this->storage->write($attachment->path(), $request->contents);

        try {
            $this->transaction->run(fn () => $this->attachments->save($attachment));
        } catch (Throwable $error) {
            // El disco no participa en la transacción: si la fila no se guarda, el archivo se borra
            // a mano. Sin esto quedarían archivos huérfanos que nadie referencia.
            $this->storage->delete($attachment->path());

            throw $error;
        }

        $primitives = $attachment->toPrimitives();

        return new AttachmentUploaderResponse($primitives['id'], $primitives['file_name'], $primitives['size_bytes']);
    }
}
