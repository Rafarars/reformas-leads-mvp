<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Reformas\Leads\Classification\Domain\LeadClassification;
use Reformas\Leads\Classification\Domain\LeadClassificationRepository;
use Reformas\Leads\Classification\Domain\LeadContact;
use Reformas\Leads\Classification\Domain\Urgency;
use Reformas\Leads\Classification\Domain\WorkCategory;
use Reformas\Leads\Classification\Infrastructure\Persistence\Eloquent\EloquentLeadClassificationRepository;
use Reformas\Leads\Classification\Infrastructure\Persistence\InMemory\InMemoryLeadClassificationRepository;
use Reformas\Leads\Lead\Domain\Lead;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Infrastructure\Persistence\Eloquent\EloquentLeadRepository;
use Tests\Unit\Leads\Classification\ClassificationProposalMother;
use Tests\Unit\Leads\Lead\Domain\LeadSubmissionMother;

uses(RefreshDatabase::class);

dataset('lead classification repositories', [
    'in memory' => fn () => new InMemoryLeadClassificationRepository,
    'eloquent' => fn () => new EloquentLeadClassificationRepository,
]);

// La clave foránea exige que el lead exista, también para el doble: así las dos suites parten igual.
function classifiableLead(int $number): LeadId
{
    $id = new LeadId(sprintf('00000000-0000-7000-8000-%012d', $number));
    (new EloquentLeadRepository)->save(Lead::receive($id, LeadSubmissionMother::create(), new DateTimeImmutable));

    return $id;
}

it('returns null when a lead has no classification', function (LeadClassificationRepository $repository) {
    expect($repository->search(new LeadId('00000000-0000-7000-8000-000000000099')))->toBeNull();
})->with('lead classification repositories');

it('gives back a classification exactly as it was, with or without a proposal', function (LeadClassificationRepository $repository) {
    $classified = LeadClassification::fromProposal(classifiableLead(1), ClassificationProposalMother::create(0.83), 0.7, new LeadContact('Ana García', 'ana@example.com', '+34600111222'), new DateTimeImmutable('2026-09-15T10:00:00+00:00'));
    $unclassified = LeadClassification::unclassified(classifiableLead(2), 'La IA no respondió.', new LeadContact('Ana García', 'ana@example.com', '+34600111222'), new DateTimeImmutable('2026-09-15T10:00:00+00:00'));

    $repository->save($classified);
    $repository->save($unclassified);

    expect($repository->search($classified->leadId())?->toPrimitives())->toBe($classified->toPrimitives())
        ->and($repository->search($unclassified->leadId())?->toPrimitives())->toBe($unclassified->toPrimitives());
})->with('lead classification repositories');

it('updates a reviewed classification instead of duplicating it', function (LeadClassificationRepository $repository) {
    $classification = LeadClassification::unclassified(classifiableLead(1), 'La IA no respondió.', new LeadContact('Ana García', 'ana@example.com', '+34600111222'), new DateTimeImmutable('2026-09-15T10:00:00+00:00'));
    $repository->save($classification);

    $classification->review(WorkCategory::Kitchen, Urgency::Low, 'ana@example.com', new DateTimeImmutable('2026-09-15T11:00:00+00:00'));
    $repository->save($classification);

    expect($repository->search($classification->leadId())?->toPrimitives())->toBe($classification->toPrimitives())
        ->and($repository->searchLatest(10))->toHaveCount(1);
})->with('lead classification repositories');

it('lists pending reviews first and then the most recent, up to the limit', function (LeadClassificationRepository $repository) {
    $repository->save(LeadClassification::fromProposal(classifiableLead(1), ClassificationProposalMother::create(), 0.7, new LeadContact('Ana García', 'ana@example.com', '+34600111222'), new DateTimeImmutable('2026-09-15T10:00:00+00:00')));
    $repository->save(LeadClassification::unclassified(classifiableLead(2), 'x', new LeadContact('Ana García', 'ana@example.com', '+34600111222'), new DateTimeImmutable('2026-09-15T09:00:00+00:00')));
    $repository->save(LeadClassification::fromProposal(classifiableLead(3), ClassificationProposalMother::create(), 0.7, new LeadContact('Ana García', 'ana@example.com', '+34600111222'), new DateTimeImmutable('2026-09-15T11:00:00+00:00')));

    $ids = array_map(static fn (LeadClassification $c): string => substr($c->leadId()->value, -1), $repository->searchLatest(2));

    expect($ids)->toBe(['2', '3']);
})->with('lead classification repositories');
