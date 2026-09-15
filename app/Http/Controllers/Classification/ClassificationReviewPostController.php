<?php

namespace App\Http\Controllers\Classification;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Reformas\Leads\Classification\Application\Review\LeadClassificationReviewer;
use Reformas\Leads\Classification\Application\Review\LeadClassificationReviewerRequest;
use Reformas\Shared\Domain\DomainError;

final class ClassificationReviewPostController
{
    private const FIELD_BY_ERROR = [
        'invalid_work_category' => 'category',
        'invalid_urgency' => 'urgency',
    ];

    public function __invoke(string $lead, Request $request, LeadClassificationReviewer $review): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string'],
            'urgency' => ['required', 'string'],
        ]);

        try {
            $review(new LeadClassificationReviewerRequest(
                $lead,
                (string) $data['category'],
                (string) $data['urgency'],
                (string) $request->user()?->email,
            ));
        } catch (DomainError $error) {
            return back()->withErrors([self::FIELD_BY_ERROR[$error->errorType()] ?? 'review' => $error->publicMessage()]);
        }

        return back();
    }
}
