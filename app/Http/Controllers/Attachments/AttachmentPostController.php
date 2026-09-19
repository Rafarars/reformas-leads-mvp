<?php

namespace App\Http\Controllers\Attachments;

use App\Http\Requests\Leads\UploadAttachmentRequest;
use Illuminate\Http\RedirectResponse;
use Reformas\Leads\Attachment\Application\Upload\AttachmentUploader;
use Reformas\Leads\Attachment\Application\Upload\AttachmentUploaderRequest;
use Reformas\Shared\Domain\DomainError;

final class AttachmentPostController
{
    public function __invoke(string $lead, UploadAttachmentRequest $request, AttachmentUploader $upload): RedirectResponse
    {
        $file = $request->file('file');

        try {
            $upload(new AttachmentUploaderRequest(
                leadId: $lead,
                kind: (string) $request->validated('kind'),
                fileName: (string) $file->getClientOriginalName(),
                contentType: (string) $file->getMimeType(),
                sizeBytes: (int) $file->getSize(),
                contents: (string) $file->get(),
            ));
        } catch (DomainError $error) {
            return back()->withErrors(['file' => $error->publicMessage()]);
        }

        return back();
    }
}
