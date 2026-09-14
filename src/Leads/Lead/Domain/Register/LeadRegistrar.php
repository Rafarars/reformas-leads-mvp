<?php

namespace Reformas\Leads\Lead\Domain\Register;

use DateTimeImmutable;
use Reformas\Leads\Lead\Domain\Lead;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Reformas\Leads\Lead\Domain\LeadSubmission;

// La regla de no duplicar: si la persona ya dejó sus datos, es un segundo contacto, no un lead nuevo.
final readonly class LeadRegistrar
{
    public function __construct(private LeadRepository $repository) {}

    public function __invoke(LeadId $newId, LeadSubmission $submission, DateTimeImmutable $now): LeadRegistration
    {
        $existing = $this->repository->searchByContact($submission->phone, $submission->email);

        if ($existing === null) {
            return new LeadRegistration(Lead::receive($newId, $submission, $now), repeated: false);
        }

        $existing->registerRepeatedContact($submission, $now);

        return new LeadRegistration($existing, repeated: true);
    }
}
