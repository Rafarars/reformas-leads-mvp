<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Reformas\Leads\Lead\Domain\EmailAddress;
use Reformas\Leads\Lead\Domain\Lead;
use Reformas\Leads\Lead\Domain\LeadHistoryEntry;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadRepository;
use Reformas\Leads\Lead\Domain\PhoneNumber;
use Reformas\Leads\Lead\Infrastructure\Persistence\Eloquent\EloquentLeadRepository;
use Reformas\Leads\Lead\Infrastructure\Persistence\InMemory\InMemoryLeadRepository;
use Tests\Unit\Leads\Lead\Domain\LeadSubmissionMother;

// Una sola suite contra el doble en memoria y contra Eloquent. Si las dos pasan, las pruebas
// del caso de uso que usan el doble no mienten sobre lo que hace la base.
uses(RefreshDatabase::class);

dataset('lead repositories', [
    'in memory' => fn () => new InMemoryLeadRepository,
    'eloquent' => fn () => new EloquentLeadRepository,
]);

function contractLead(): Lead
{
    return Lead::receive(
        new LeadId('00000000-0000-7000-8000-000000000001'),
        LeadSubmissionMother::create(),
        new DateTimeImmutable('2026-09-14T10:00:00+00:00'),
    );
}

/**
 * @return list<string>
 */
function contractHistoryTypes(LeadRepository $repository, LeadId $id): array
{
    return array_map(static fn (LeadHistoryEntry $entry): string => $entry->type->value, $repository->history($id));
}

it('returns null for a lead that does not exist', function (LeadRepository $repository) {
    expect($repository->search(new LeadId('00000000-0000-7000-8000-000000000099')))->toBeNull();
})->with('lead repositories');

it('gives back a saved lead exactly as it was', function (LeadRepository $repository) {
    $lead = contractLead();
    $expected = $lead->toPrimitives();

    $repository->save($lead);

    expect($repository->search($lead->id())?->toPrimitives())->toBe($expected);
})->with('lead repositories');

it('keeps optional fields empty when they were not sent', function (LeadRepository $repository) {
    $lead = Lead::receive(new LeadId('00000000-0000-7000-8000-000000000001'), LeadSubmissionMother::withoutOptionalFields(), new DateTimeImmutable);
    $repository->save($lead);

    $stored = $repository->search($lead->id())?->toPrimitives();

    expect($stored['zone'])->toBeNull()
        ->and($stored['budget'])->toBeNull()
        ->and($stored['description'])->toBeNull();
})->with('lead repositories');

it('updates a lead saved again instead of duplicating it', function (LeadRepository $repository) {
    $lead = contractLead();
    $primitives = $lead->toPrimitives();
    $repository->save($lead);

    $stored = $repository->search($lead->id());
    $stored->registerRepeatedContact(LeadSubmissionMother::create(), new DateTimeImmutable('2026-09-14T11:00:00+00:00'));
    $repository->save($stored);

    $found = $repository->searchByContact(new PhoneNumber($primitives['phone']), new EmailAddress($primitives['email']));

    expect($found?->id()->value)->toBe($primitives['id'])
        ->and($found?->toPrimitives()['contact_count'])->toBe(2);
})->with('lead repositories');

it('finds a lead by its phone or by its email', function (LeadRepository $repository) {
    $lead = contractLead();
    $primitives = $lead->toPrimitives();
    $repository->save($lead);

    $unknownEmail = new EmailAddress('nadie@example.com');
    $unknownPhone = new PhoneNumber('+34699999999');

    expect($repository->searchByContact(new PhoneNumber($primitives['phone']), $unknownEmail)?->id()->value)->toBe($primitives['id'])
        ->and($repository->searchByContact($unknownPhone, new EmailAddress($primitives['email']))?->id()->value)->toBe($primitives['id'])
        ->and($repository->searchByContact($unknownPhone, $unknownEmail))->toBeNull();
})->with('lead repositories');

it('appends only the new history entries, in order', function (LeadRepository $repository) {
    $lead = contractLead();
    $repository->save($lead);

    $stored = $repository->search($lead->id());
    $stored->registerRepeatedContact(LeadSubmissionMother::create(), new DateTimeImmutable('2026-09-14T11:00:00+00:00'));
    $repository->save($stored);
    // Guardar otra vez sin cambios no debe repetir entradas.
    $repository->save($stored);

    $history = $repository->history($lead->id());

    expect(contractHistoryTypes($repository, $lead->id()))->toBe(['received', 'repeated_contact'])
        ->and(array_map(static fn (LeadHistoryEntry $entry): string => $entry->toPrimitives()['occurred_at'], $history))
        ->toBe(['2026-09-14T10:00:00+00:00', '2026-09-14T11:00:00+00:00']);
})->with('lead repositories');
