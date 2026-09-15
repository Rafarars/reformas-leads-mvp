<?php

namespace Reformas\Leads\Classification\Application\ApproveDraft;

final readonly class CustomerMessageDraftApproverRequest
{
    public function __construct(
        public string $leadId,
        public string $message,
        public string $approver,
    ) {}
}
