<?php

use Reformas\Leads\Attachment\Domain\AttachmentContentType;
use Reformas\Leads\Attachment\Domain\AttachmentFileName;
use Reformas\Leads\Attachment\Domain\AttachmentId;
use Reformas\Leads\Attachment\Domain\AttachmentKind;
use Reformas\Leads\Attachment\Domain\AttachmentSize;
use Reformas\Leads\Attachment\Domain\InvalidAttachmentContentTypeError;
use Reformas\Leads\Attachment\Domain\InvalidAttachmentFileNameError;
use Reformas\Leads\Attachment\Domain\InvalidAttachmentKindError;
use Reformas\Leads\Attachment\Domain\InvalidAttachmentSizeError;
use Reformas\Leads\Attachment\Domain\LeadAttachment;
use Reformas\Leads\Lead\Domain\LeadId;

const ATTACHMENT_ID = '00000000-0000-7000-8000-0000000000a1';
const ATTACHED_LEAD = '00000000-0000-7000-8000-000000000001';

function uploadedAttachment(string $fileName = 'cocina.jpg', string $contentType = 'image/jpeg'): LeadAttachment
{
    return LeadAttachment::upload(
        new AttachmentId(ATTACHMENT_ID),
        new LeadId(ATTACHED_LEAD),
        AttachmentKind::Photo,
        new AttachmentFileName($fileName),
        new AttachmentContentType($contentType),
        new AttachmentSize(2048),
        new DateTimeImmutable('2026-09-19T10:00:00+00:00'),
    );
}

it('builds the path from identifiers, never from the name that was sent', function () {
    $attachment = uploadedAttachment('../../etc/passwd.jpg');

    expect($attachment->path())->toBe('leads/'.ATTACHED_LEAD.'/'.ATTACHMENT_ID.'.jpg')
        // El nombre original se guarda limpio, solo para mostrarlo y descargarlo.
        ->and($attachment->fileName()->value)->toBe('.. .. etc passwd.jpg');
});

it('uses the extension of the content type, not the one of the file name', function () {
    expect(uploadedAttachment('plano.jpg', 'application/pdf')->path())->toEndWith('.pdf');
});

it('survives a round trip through primitives', function () {
    $attachment = uploadedAttachment();

    expect(LeadAttachment::fromPrimitives($attachment->toPrimitives())->toPrimitives())->toBe($attachment->toPrimitives())
        ->and($attachment->toPrimitives())->toMatchArray([
            'kind' => 'photo',
            'file_name' => 'cocina.jpg',
            'content_type' => 'image/jpeg',
            'size_bytes' => 2048,
            'uploaded_at' => '2026-09-19T10:00:00+00:00',
        ]);
});

it('rejects what does not meet the contract', function (Closure $build, string $error) {
    expect($build)->toThrow($error);
})->with([
    'unknown kind' => [fn () => AttachmentKind::fromValue('video'), InvalidAttachmentKindError::class],
    'executable' => [fn () => new AttachmentContentType('application/x-msdownload'), InvalidAttachmentContentTypeError::class],
    'svg (can carry scripts)' => [fn () => new AttachmentContentType('image/svg+xml'), InvalidAttachmentContentTypeError::class],
    'empty file' => [fn () => new AttachmentSize(0), InvalidAttachmentSizeError::class],
    'over 10 MB' => [fn () => new AttachmentSize(AttachmentSize::MAX_BYTES + 1), InvalidAttachmentSizeError::class],
    'name with only slashes' => [fn () => new AttachmentFileName('///'), InvalidAttachmentFileNameError::class],
]);

it('accepts the formats a reform quote needs', function () {
    expect(AttachmentContentType::allowed())->toBe(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
        ->and((new AttachmentSize(AttachmentSize::MAX_BYTES))->bytes)->toBe(10 * 1024 * 1024);
});
