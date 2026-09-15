<?php

use Reformas\Leads\Classification\Domain\ClassificationConfidence;
use Reformas\Leads\Classification\Domain\ClassificationReason;
use Reformas\Leads\Classification\Domain\CustomerMessageApprovedDomainEvent;
use Reformas\Leads\Classification\Domain\CustomerMessageDraft;
use Reformas\Leads\Classification\Domain\CustomerMessageDraftNotPendingError;
use Reformas\Leads\Classification\Domain\InvalidClassificationConfidenceError;
use Reformas\Leads\Classification\Domain\InvalidClassificationReasonError;
use Reformas\Leads\Classification\Domain\InvalidCustomerMessageDraftError;
use Reformas\Leads\Classification\Domain\InvalidLeadFindingsError;
use Reformas\Leads\Classification\Domain\InvalidProjectSummaryError;
use Reformas\Leads\Classification\Domain\InvalidUrgencyError;
use Reformas\Leads\Classification\Domain\InvalidWorkCategoryError;
use Reformas\Leads\Classification\Domain\LeadClassification;
use Reformas\Leads\Classification\Domain\LeadFindings;
use Reformas\Leads\Classification\Domain\ProjectSummary;
use Reformas\Leads\Classification\Domain\Urgency;
use Reformas\Leads\Classification\Domain\WorkCategory;
use Reformas\Leads\Lead\Domain\LeadId;
use Tests\Unit\Leads\Classification\ClassificationProposalMother;

const CLASSIFIED_LEAD = '00000000-0000-7000-8000-000000000001';

it('accepts the proposal when the model is confident enough', function () {
    $classification = LeadClassification::fromProposal(new LeadId(CLASSIFIED_LEAD), ClassificationProposalMother::create(0.9), 0.7, new DateTimeImmutable);

    expect($classification->toPrimitives())->toMatchArray([
        'status' => 'classified',
        'category' => 'bathroom',
        'urgency' => 'high',
        'confidence' => 0.9,
        'model' => 'gemini-3.5-flash',
        'review_reason' => null,
    ]);
});

it('keeps a doubtful proposal but sends it to a person', function () {
    $classification = LeadClassification::fromProposal(new LeadId(CLASSIFIED_LEAD), ClassificationProposalMother::create(0.69), 0.7, new DateTimeImmutable);

    expect($classification->toPrimitives())->toMatchArray([
        'status' => 'needs_review',
        'category' => 'bathroom',
        'review_reason' => 'La IA no está segura de la clasificación.',
    ]);
});

it('does not send a proposal exactly at the threshold to review', function () {
    $classification = LeadClassification::fromProposal(new LeadId(CLASSIFIED_LEAD), ClassificationProposalMother::create(0.7), 0.7, new DateTimeImmutable);

    expect($classification->toPrimitives()['status'])->toBe('classified');
});

it('sends a lead without a usable proposal to a person, without inventing a category', function () {
    $classification = LeadClassification::unclassified(new LeadId(CLASSIFIED_LEAD), 'La IA no respondió.', new DateTimeImmutable);

    expect($classification->toPrimitives())->toMatchArray([
        'status' => 'needs_review',
        'category' => null,
        'urgency' => null,
        'confidence' => null,
        'review_reason' => 'La IA no respondió.',
    ]);
});

it('lets a person override the model and keeps what the model said', function () {
    $classification = LeadClassification::fromProposal(new LeadId(CLASSIFIED_LEAD), ClassificationProposalMother::create(0.5), 0.7, new DateTimeImmutable('2026-09-15T10:00:00+00:00'));

    $classification->review(WorkCategory::Installations, Urgency::Medium, 'ana@example.com', new DateTimeImmutable('2026-09-15T11:00:00+00:00'));

    expect($classification->toPrimitives())->toMatchArray([
        'status' => 'reviewed',
        'category' => 'installations',
        'urgency' => 'medium',
        'confidence' => 0.5,
        'reason' => 'Fuga activa bajo la ducha: baño y urgente.',
        'review_reason' => null,
        'reviewed_by' => 'ana@example.com',
        'created_at' => '2026-09-15T10:00:00+00:00',
        'updated_at' => '2026-09-15T11:00:00+00:00',
    ]);
});

it('survives a round trip through primitives', function () {
    $classification = LeadClassification::fromProposal(new LeadId(CLASSIFIED_LEAD), ClassificationProposalMother::create(), 0.7, new DateTimeImmutable('2026-09-15T10:00:00+00:00'));

    expect(LeadClassification::fromPrimitives($classification->toPrimitives())->toPrimitives())->toBe($classification->toPrimitives());
});

it('rejects values outside the contract', function (Closure $build, string $error) {
    expect($build)->toThrow($error);
})->with([
    'confidence above 1' => [fn () => new ClassificationConfidence(1.01), InvalidClassificationConfidenceError::class],
    'negative confidence' => [fn () => new ClassificationConfidence(-0.1), InvalidClassificationConfidenceError::class],
    'empty reason' => [fn () => new ClassificationReason('   '), InvalidClassificationReasonError::class],
    'too long reason' => [fn () => new ClassificationReason(str_repeat('a', 501)), InvalidClassificationReasonError::class],
    'unknown category' => [fn () => WorkCategory::fromValue('roof'), InvalidWorkCategoryError::class],
    'unknown urgency' => [fn () => Urgency::fromValue('asap'), InvalidUrgencyError::class],
    'empty summary' => [fn () => new ProjectSummary(''), InvalidProjectSummaryError::class],
    'too many findings' => [fn () => new LeadFindings(['a', 'b', 'c', 'd', 'e', 'f']), InvalidLeadFindingsError::class],
    'blank finding' => [fn () => new LeadFindings(['Medidas', ' ']), InvalidLeadFindingsError::class],
    'too long message' => [fn () => new CustomerMessageDraft(str_repeat('a', 1501)), InvalidCustomerMessageDraftError::class],
]);

it('accepts the limits of the contract', function () {
    expect((new ClassificationConfidence(0.0))->value)->toBe(0.0)
        ->and((new ClassificationConfidence(1.0))->value)->toBe(1.0)
        ->and((new ClassificationReason(str_repeat('a', 500)))->value)->toHaveLength(500)
        ->and((new LeadFindings(['a', 'b', 'c', 'd', 'e']))->items)->toHaveCount(5)
        ->and((new LeadFindings([]))->items)->toBe([])
        ->and((new CustomerMessageDraft(str_repeat('a', 1500)))->value)->toHaveLength(1500);
});

it('keeps the insights and a pending draft from the proposal', function () {
    $classification = LeadClassification::fromProposal(new LeadId(CLASSIFIED_LEAD), ClassificationProposalMother::create(), 0.7, new DateTimeImmutable);

    expect($classification->toPrimitives())->toMatchArray([
        'summary' => 'Cambiar el plato de ducha por una fuga que afecta al vecino.',
        'missing_information' => ['Medidas del baño', 'Fotos del estado actual'],
        'contradictions' => [],
        'draft_status' => 'pending',
        'draft_approved_by' => null,
    ])->and($classification->pullDomainEvents())->toBe([]);
});

it('approves the text the person settled on and announces it', function () {
    $classification = LeadClassification::fromProposal(new LeadId(CLASSIFIED_LEAD), ClassificationProposalMother::create(), 0.7, new DateTimeImmutable('2026-09-15T10:00:00+00:00'));

    $classification->approveDraft(new CustomerMessageDraft('Hola Ana, texto corregido.'), 'ana@example.com', new DateTimeImmutable('2026-09-15T12:00:00+00:00'));

    $events = $classification->pullDomainEvents();

    expect($classification->toPrimitives())->toMatchArray([
        'draft_message' => 'Hola Ana, texto corregido.',
        'draft_status' => 'approved',
        'draft_approved_by' => 'ana@example.com',
        'draft_approved_at' => '2026-09-15T12:00:00+00:00',
    ])
        ->and($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(CustomerMessageApprovedDomainEvent::class)
        ->and($events[0]->body())->toBe(['lead_id' => CLASSIFIED_LEAD, 'message' => 'Hola Ana, texto corregido.', 'approved_by' => 'ana@example.com']);
});

it('refuses to approve twice or when there is no draft', function (Closure $classification) {
    $classification()->approveDraft(new CustomerMessageDraft('Hola'), 'ana@example.com', new DateTimeImmutable);
})->with([
    'already approved' => [function () {
        $classification = LeadClassification::fromProposal(new LeadId(CLASSIFIED_LEAD), ClassificationProposalMother::create(), 0.7, new DateTimeImmutable);
        $classification->approveDraft(new CustomerMessageDraft('Hola'), 'ana@example.com', new DateTimeImmutable);

        return $classification;
    }],
    'no proposal' => [fn () => LeadClassification::unclassified(new LeadId(CLASSIFIED_LEAD), 'x', new DateTimeImmutable)],
])->throws(CustomerMessageDraftNotPendingError::class);
