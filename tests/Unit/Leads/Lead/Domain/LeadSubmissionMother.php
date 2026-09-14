<?php

namespace Tests\Unit\Leads\Lead\Domain;

use Reformas\Leads\Lead\Application\Receive\LeadReceiverRequest;
use Reformas\Leads\Lead\Domain\Budget;
use Reformas\Leads\Lead\Domain\EmailAddress;
use Reformas\Leads\Lead\Domain\LeadDescription;
use Reformas\Leads\Lead\Domain\LeadName;
use Reformas\Leads\Lead\Domain\LeadSource;
use Reformas\Leads\Lead\Domain\LeadSubmission;
use Reformas\Leads\Lead\Domain\LeadZone;
use Reformas\Leads\Lead\Domain\PhoneNumber;
use Reformas\Leads\Lead\Domain\RenovationType;
use Tests\Unit\Leads\Lead\LeadReceiverRequestMother;

final class LeadSubmissionMother
{
    public static function create(?string $zone = null, ?int $budget = null, ?string $description = null): LeadSubmission
    {
        return self::fromRequest(LeadReceiverRequestMother::create(zone: $zone, budget: $budget, description: $description));
    }

    public static function withoutOptionalFields(): LeadSubmission
    {
        return self::fromRequest(LeadReceiverRequestMother::withoutOptionalFields());
    }

    private static function fromRequest(LeadReceiverRequest $request): LeadSubmission
    {
        return new LeadSubmission(
            new LeadName($request->name),
            new PhoneNumber($request->phone),
            new EmailAddress($request->email),
            RenovationType::from($request->renovationType),
            $request->zone === null ? null : new LeadZone($request->zone),
            $request->budget === null ? null : new Budget($request->budget),
            $request->description === null ? null : new LeadDescription($request->description),
            new LeadSource($request->source),
        );
    }
}
