<?php

namespace App\Http\Controllers\Api\V1\Leads;

use App\Http\Requests\Leads\UploadAttachmentRequest;
use Illuminate\Http\JsonResponse;
use Reformas\Leads\Attachment\Application\Upload\AttachmentUploader;
use Reformas\Leads\Attachment\Application\Upload\AttachmentUploaderRequest;

// Endpoint aparte del alta del lead: subir una foto es lento y puede fallar, y el lead ya existe.
final class LeadAttachmentPostController
{
    public function __invoke(string $lead, UploadAttachmentRequest $request, AttachmentUploader $upload): JsonResponse
    {
        $file = $request->file('file');

        $response = $upload(new AttachmentUploaderRequest(
            leadId: $lead,
            kind: (string) $request->validated('kind'),
            fileName: (string) $file->getClientOriginalName(),
            contentType: (string) $file->getMimeType(),
            sizeBytes: (int) $file->getSize(),
            contents: (string) $file->get(),
        ));

        return new JsonResponse($response->toArray(), 201);
    }
}
