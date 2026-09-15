<?php

namespace App\Http\Controllers\Classification;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Reformas\Leads\Classification\Application\ApproveDraft\CustomerMessageDraftApprover;
use Reformas\Leads\Classification\Application\ApproveDraft\CustomerMessageDraftApproverRequest;
use Reformas\Shared\Domain\DomainError;

final class ClassificationDraftApprovePostController
{
    public function __invoke(string $lead, Request $request, CustomerMessageDraftApprover $approve): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string']]);

        try {
            $approve(new CustomerMessageDraftApproverRequest($lead, (string) $data['message'], (string) $request->user()?->email));
        } catch (DomainError $error) {
            return back()->withErrors(['message' => $error->publicMessage()]);
        }

        return back();
    }
}
