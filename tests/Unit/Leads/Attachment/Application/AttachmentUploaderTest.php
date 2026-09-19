<?php

use Reformas\Leads\Attachment\Application\Download\AttachmentReader;
use Reformas\Leads\Attachment\Application\Download\AttachmentReaderRequest;
use Reformas\Leads\Attachment\Application\Search\LeadAttachmentsSearcher;
use Reformas\Leads\Attachment\Application\Upload\AttachmentUploader;
use Reformas\Leads\Attachment\Application\Upload\AttachmentUploaderRequest;
use Reformas\Leads\Attachment\Domain\AttachmentNotFoundError;
use Reformas\Leads\Attachment\Domain\InvalidAttachmentContentTypeError;
use Reformas\Leads\Attachment\Infrastructure\Persistence\InMemory\InMemoryAttachmentRepository;
use Reformas\Leads\Lead\Domain\Lead;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadNotFoundError;
use Reformas\Leads\Lead\Infrastructure\Persistence\InMemory\InMemoryLeadRepository;
use Reformas\Shared\Domain\Transaction;
use Tests\Unit\Leads\Attachment\InMemoryAttachmentStorage;
use Tests\Unit\Leads\Lead\Domain\LeadSubmissionMother;
use Tests\Unit\Shared\FixedClock;
use Tests\Unit\Shared\SequentialUuidGenerator;
use Tests\Unit\Shared\SpyTransaction;

const LEAD_WITH_ATTACHMENTS = '00000000-0000-7000-8000-000000000001';

beforeEach(function () {
    $this->attachments = new InMemoryAttachmentRepository;
    $this->storage = new InMemoryAttachmentStorage;
    $this->leads = new InMemoryLeadRepository;
    $this->clock = new FixedClock;
    // Un generador por prueba: uno nuevo en cada subida daría el mismo identificador y se pisarían.
    $this->uuid = new SequentialUuidGenerator;
    $this->leads->save(Lead::receive(new LeadId(LEAD_WITH_ATTACHMENTS), LeadSubmissionMother::create(), $this->clock->now()));
});

function uploadAttachment(object $test, Transaction $transaction, array $overrides = []): void
{
    $upload = new AttachmentUploader($test->attachments, $test->storage, $test->leads, $transaction, $test->clock, $test->uuid);

    $upload(new AttachmentUploaderRequest(
        leadId: $overrides['leadId'] ?? LEAD_WITH_ATTACHMENTS,
        kind: $overrides['kind'] ?? 'photo',
        fileName: $overrides['fileName'] ?? 'cocina.jpg',
        contentType: $overrides['contentType'] ?? 'image/jpeg',
        sizeBytes: $overrides['sizeBytes'] ?? 2048,
        contents: $overrides['contents'] ?? 'contenido binario',
    ));
}

// Una transacción que siempre falla: el caso que deja archivos huérfanos si nadie limpia.
final class FailingTransaction implements Transaction
{
    public function run(callable $operation): mixed
    {
        throw new RuntimeException('La base rechazó la fila');
    }
}

it('stores the file and its row', function () {
    uploadAttachment($this, new SpyTransaction);

    $stored = $this->attachments->searchByLead(new LeadId(LEAD_WITH_ATTACHMENTS));

    expect($stored)->toHaveCount(1)
        ->and($stored[0]->toPrimitives()['file_name'])->toBe('cocina.jpg')
        ->and($this->storage->files)->toHaveKey($stored[0]->path())
        ->and($this->storage->files[$stored[0]->path()])->toBe('contenido binario');
});

it('deletes the file when the row could not be saved', function () {
    expect(fn () => uploadAttachment($this, new FailingTransaction))->toThrow(RuntimeException::class)
        // Ni fila ni archivo: el disco no participa en la transacción, así que se limpia a mano.
        ->and($this->attachments->searchByLead(new LeadId(LEAD_WITH_ATTACHMENTS)))->toBe([])
        ->and($this->storage->files)->toBe([]);
});

it('writes nothing when the file does not meet the contract', function () {
    $transaction = new SpyTransaction;

    expect(fn () => uploadAttachment($this, $transaction, ['contentType' => 'application/x-msdownload']))
        ->toThrow(InvalidAttachmentContentTypeError::class)
        ->and($this->storage->files)->toBe([])
        ->and($transaction->runs)->toBe(0);
});

it('refuses to attach anything to a lead that does not exist', function () {
    $transaction = new SpyTransaction;

    expect(fn () => uploadAttachment($this, $transaction, ['leadId' => '00000000-0000-7000-8000-000000000099']))
        ->toThrow(LeadNotFoundError::class)
        ->and($this->storage->files)->toBe([])
        ->and($transaction->runs)->toBe(0);
});

it('lists the attachments of a lead without exposing where they are stored', function () {
    uploadAttachment($this, new SpyTransaction);
    $this->clock->advance('+1 minute');
    uploadAttachment($this, new SpyTransaction, ['kind' => 'plan', 'fileName' => 'plano.pdf', 'contentType' => 'application/pdf']);

    $rows = (new LeadAttachmentsSearcher($this->attachments))(LEAD_WITH_ATTACHMENTS);

    expect(array_column($rows, 'kind'))->toBe(['photo', 'plan'])
        ->and(array_column($rows, 'file_name'))->toBe(['cocina.jpg', 'plano.pdf'])
        ->and($rows[0])->not->toHaveKey('path');
});

it('gives back the file to download it', function () {
    uploadAttachment($this, new SpyTransaction);
    $id = $this->attachments->searchByLead(new LeadId(LEAD_WITH_ATTACHMENTS))[0]->toPrimitives()['id'];

    $file = (new AttachmentReader($this->attachments, $this->storage))(new AttachmentReaderRequest($id));

    expect([$file->contents, $file->fileName, $file->contentType])->toBe(['contenido binario', 'cocina.jpg', 'image/jpeg']);
});

it('reports a missing attachment, and also a row whose file disappeared', function () {
    $read = new AttachmentReader($this->attachments, $this->storage);
    uploadAttachment($this, new SpyTransaction);
    $attachment = $this->attachments->searchByLead(new LeadId(LEAD_WITH_ATTACHMENTS))[0];
    $this->storage->delete($attachment->path());

    expect(fn () => $read(new AttachmentReaderRequest('00000000-0000-7000-8000-0000000000ff')))->toThrow(AttachmentNotFoundError::class)
        ->and(fn () => $read(new AttachmentReaderRequest($attachment->toPrimitives()['id'])))->toThrow(AttachmentNotFoundError::class);
});
