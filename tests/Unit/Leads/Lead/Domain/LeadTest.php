<?php

use Reformas\Leads\Lead\Domain\Lead;
use Reformas\Leads\Lead\Domain\LeadHistoryEntry;
use Reformas\Leads\Lead\Domain\LeadId;
use Tests\Unit\Leads\Lead\Domain\LeadSubmissionMother;

function historyTypes(Lead $lead): array
{
    return array_map(static fn (LeadHistoryEntry $entry): string => $entry->type->value, $lead->pullPendingHistory());
}

it('receives a new lead with a single contact and its history entry', function () {
    $now = new DateTimeImmutable('2026-09-14T10:00:00+00:00');

    $lead = Lead::receive(new LeadId('00000000-0000-7000-8000-000000000001'), LeadSubmissionMother::create(), $now);
    $primitives = $lead->toPrimitives();

    expect($primitives['status'])->toBe('received')
        ->and($primitives['contact_count'])->toBe(1)
        ->and($primitives['received_at'])->toBe($now->format(DATE_ATOM))
        ->and(historyTypes($lead))->toBe(['received']);
});

it('completes missing data on a repeated contact without overwriting what was known', function () {
    $lead = Lead::receive(
        new LeadId('00000000-0000-7000-8000-000000000001'),
        LeadSubmissionMother::withoutOptionalFields(),
        new DateTimeImmutable('2026-09-14T10:00:00+00:00'),
    );

    $lead->registerRepeatedContact(
        LeadSubmissionMother::create(zone: 'Madrid', budget: 20_000, description: 'Cocina completa'),
        new DateTimeImmutable('2026-09-14T11:00:00+00:00'),
    );
    $lead->registerRepeatedContact(
        LeadSubmissionMother::create(zone: 'Valencia', budget: 90_000, description: 'Otra cosa'),
        new DateTimeImmutable('2026-09-14T12:00:00+00:00'),
    );

    $primitives = $lead->toPrimitives();

    expect($primitives['zone'])->toBe('Madrid')
        ->and($primitives['budget'])->toBe(20_000)
        ->and($primitives['description'])->toBe('Cocina completa')
        ->and($primitives['contact_count'])->toBe(3)
        ->and($primitives['last_contact_at'])->toBe('2026-09-14T12:00:00+00:00')
        ->and(historyTypes($lead))->toBe(['received', 'repeated_contact', 'repeated_contact']);
});

it('hands over pending history only once', function () {
    $lead = Lead::receive(new LeadId('00000000-0000-7000-8000-000000000001'), LeadSubmissionMother::create(), new DateTimeImmutable);

    expect($lead->pullPendingHistory())->toHaveCount(1)
        ->and($lead->pullPendingHistory())->toBe([]);
});

it('survives a round trip through primitives', function () {
    $lead = Lead::receive(new LeadId('00000000-0000-7000-8000-000000000001'), LeadSubmissionMother::create(), new DateTimeImmutable);

    expect(Lead::fromPrimitives($lead->toPrimitives())->toPrimitives())->toBe($lead->toPrimitives());
});
