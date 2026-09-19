<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Reformas\Leads\Attachment\Domain\AttachmentContentType;
use Reformas\Leads\Attachment\Domain\AttachmentFileName;
use Reformas\Leads\Attachment\Domain\AttachmentId;
use Reformas\Leads\Attachment\Domain\AttachmentKind;
use Reformas\Leads\Attachment\Domain\AttachmentRepository;
use Reformas\Leads\Attachment\Domain\AttachmentSize;
use Reformas\Leads\Attachment\Domain\LeadAttachment;
use Reformas\Leads\Attachment\Infrastructure\Persistence\Eloquent\EloquentAttachmentRepository;
use Reformas\Leads\Attachment\Infrastructure\Persistence\InMemory\InMemoryAttachmentRepository;
use Reformas\Leads\Lead\Domain\Lead;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Infrastructure\Persistence\Eloquent\EloquentLeadRepository;
use Tests\Unit\Leads\Lead\Domain\LeadSubmissionMother;

uses(RefreshDatabase::class);

dataset('attachment repositories', [
    'in memory' => fn () => new InMemoryAttachmentRepository,
    'eloquent' => fn () => new EloquentAttachmentRepository,
]);

// La clave foránea exige que el lead exista, también para el doble: las dos suites parten igual.
function leadWithAttachments(int $number = 1): LeadId
{
    $id = new LeadId(sprintf('00000000-0000-7000-8000-%012d', $number));
    (new EloquentLeadRepository)->save(Lead::receive($id, LeadSubmissionMother::create(), new DateTimeImmutable));

    return $id;
}

function contractAttachment(LeadId $leadId, int $number, string $time = '2026-09-19T10:00:00+00:00'): LeadAttachment
{
    return LeadAttachment::upload(
        new AttachmentId(sprintf('00000000-0000-7000-8000-%012d', 900 + $number)),
        $leadId,
        $number % 2 === 0 ? AttachmentKind::Plan : AttachmentKind::Photo,
        new AttachmentFileName('archivo-'.$number.'.jpg'),
        new AttachmentContentType('image/jpeg'),
        new AttachmentSize(1024 * $number),
        new DateTimeImmutable($time),
    );
}

it('returns null for an attachment that does not exist', function (AttachmentRepository $repository) {
    expect($repository->search(new AttachmentId('00000000-0000-7000-8000-0000000000ff')))->toBeNull();
})->with('attachment repositories');

it('gives back a saved attachment exactly as it was', function (AttachmentRepository $repository) {
    $attachment = contractAttachment(leadWithAttachments(), 1);

    $repository->save($attachment);

    expect($repository->search($attachment->id())?->toPrimitives())->toBe($attachment->toPrimitives());
})->with('attachment repositories');

it('lists the attachments of each lead, oldest first', function (AttachmentRepository $repository) {
    $lead = leadWithAttachments(1);
    $other = leadWithAttachments(2);

    $repository->save(contractAttachment($lead, 2, '2026-09-19T11:00:00+00:00'));
    $repository->save(contractAttachment($lead, 1, '2026-09-19T10:00:00+00:00'));
    $repository->save(contractAttachment($other, 3));

    expect(array_map(static fn (LeadAttachment $a): string => $a->fileName()->value, $repository->searchByLead($lead)))
        ->toBe(['archivo-1.jpg', 'archivo-2.jpg'])
        ->and($repository->searchByLead($other))->toHaveCount(1);
})->with('attachment repositories');
