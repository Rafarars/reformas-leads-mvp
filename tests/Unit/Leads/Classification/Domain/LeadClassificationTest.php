<?php

use Reformas\Leads\Classification\Domain\ClassificationConfidence;
use Reformas\Leads\Classification\Domain\ClassificationReason;
use Reformas\Leads\Classification\Domain\InvalidClassificationConfidenceError;
use Reformas\Leads\Classification\Domain\InvalidClassificationReasonError;
use Reformas\Leads\Classification\Domain\InvalidUrgencyError;
use Reformas\Leads\Classification\Domain\InvalidWorkCategoryError;
use Reformas\Leads\Classification\Domain\LeadClassification;
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
]);

it('accepts the limits of the contract', function () {
    expect((new ClassificationConfidence(0.0))->value)->toBe(0.0)
        ->and((new ClassificationConfidence(1.0))->value)->toBe(1.0)
        ->and((new ClassificationReason(str_repeat('a', 500)))->value)->toHaveLength(500);
});
