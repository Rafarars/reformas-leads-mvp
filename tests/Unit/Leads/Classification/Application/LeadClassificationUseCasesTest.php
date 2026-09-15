<?php

use Reformas\Leads\Classification\Application\Fail\LeadClassificationFailureRecorder;
use Reformas\Leads\Classification\Application\Fail\LeadClassificationFailureRecorderRequest;
use Reformas\Leads\Classification\Application\Generate\ClassifyLeadOnLeadReceived;
use Reformas\Leads\Classification\Application\Generate\LeadClassificationGenerator;
use Reformas\Leads\Classification\Application\Generate\LeadClassificationGeneratorRequest;
use Reformas\Leads\Classification\Application\Review\LeadClassificationReviewer;
use Reformas\Leads\Classification\Application\Review\LeadClassificationReviewerRequest;
use Reformas\Leads\Classification\Application\Search\LeadClassificationsSearcher;
use Reformas\Leads\Classification\Domain\ClassificationQueue;
use Reformas\Leads\Classification\Domain\ClassifierNotConfiguredException;
use Reformas\Leads\Classification\Domain\ClassifierUnavailableException;
use Reformas\Leads\Classification\Domain\InvalidClassifierOutputException;
use Reformas\Leads\Classification\Domain\InvalidWorkCategoryError;
use Reformas\Leads\Classification\Domain\LeadClassificationNotFoundError;
use Reformas\Leads\Classification\Domain\LeadClassifier;
use Reformas\Leads\Classification\Domain\WorkCategory;
use Reformas\Leads\Classification\Infrastructure\Persistence\InMemory\InMemoryLeadClassificationRepository;
use Reformas\Leads\Lead\Domain\Lead;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadNotFoundError;
use Reformas\Leads\Lead\Domain\LeadReceivedDomainEvent;
use Reformas\Leads\Lead\Infrastructure\Persistence\InMemory\InMemoryLeadRepository;
use Tests\Unit\Leads\Classification\CallbackLeadClassifier;
use Tests\Unit\Leads\Classification\ClassificationProposalMother;
use Tests\Unit\Leads\Lead\Domain\LeadSubmissionMother;
use Tests\Unit\Shared\FixedClock;

const LEAD_TO_CLASSIFY = '00000000-0000-7000-8000-000000000001';

beforeEach(function () {
    $this->leads = new InMemoryLeadRepository;
    $this->classifications = new InMemoryLeadClassificationRepository;
    $this->clock = new FixedClock;

    $this->leads->save(Lead::receive(new LeadId(LEAD_TO_CLASSIFY), LeadSubmissionMother::create(zone: 'Valencia', budget: 8_500, description: 'Fuga bajo la ducha'), $this->clock->now()));
});

function generateClassification(object $test, LeadClassifier $classifier, string $leadId = LEAD_TO_CLASSIFY): void
{
    (new LeadClassificationGenerator($test->leads, $test->classifications, $classifier, $test->clock, 0.7))(new LeadClassificationGeneratorRequest($leadId));
}

function storedClassification(object $test): ?array
{
    return $test->classifications->search(new LeadId(LEAD_TO_CLASSIFY))?->toPrimitives();
}

it('classifies a lead with what the model proposes', function () {
    generateClassification($this, CallbackLeadClassifier::proposing(ClassificationProposalMother::create()));

    expect(storedClassification($this))->toMatchArray(['status' => 'classified', 'category' => 'bathroom', 'urgency' => 'high']);
});

it('sends only the renovation data to the model, never who the person is', function () {
    $classifier = CallbackLeadClassifier::proposing(ClassificationProposalMother::create());

    generateClassification($this, $classifier);

    expect(get_object_vars($classifier->received[0]))->toBe([
        'renovationType' => $this->leads->search(new LeadId(LEAD_TO_CLASSIFY))->toPrimitives()['renovation_type'],
        'zone' => 'Valencia',
        'budget' => 8_500,
        'description' => 'Fuga bajo la ducha',
    ]);
});

it('does not ask the model again when the lead is already classified', function () {
    generateClassification($this, CallbackLeadClassifier::proposing(ClassificationProposalMother::create()));
    $second = CallbackLeadClassifier::proposing(ClassificationProposalMother::create(category: WorkCategory::Kitchen));

    generateClassification($this, $second);

    expect($second->received)->toBe([])
        ->and(storedClassification($this)['category'])->toBe('bathroom');
});

it('sends the lead to a person when the model answers outside the contract', function () {
    generateClassification($this, CallbackLeadClassifier::throwing(new InvalidClassifierOutputException('texto libre')));

    expect(storedClassification($this))->toMatchArray(['status' => 'needs_review', 'category' => null, 'review_reason' => 'La IA respondió algo que no se pudo validar.']);
});

it('sends the lead to a person at once when there is no model configured', function () {
    generateClassification($this, CallbackLeadClassifier::throwing(new ClassifierNotConfiguredException('Falta GEMINI_API_KEY.')));

    expect(storedClassification($this))->toMatchArray(['status' => 'needs_review', 'review_reason' => 'La clasificación automática no está configurada o el modelo rechazó la petición.']);
});

it('lets a temporary outage bubble up so the queue retries, saving nothing', function () {
    expect(fn () => generateClassification($this, CallbackLeadClassifier::throwing(new ClassifierUnavailableException('HTTP 503'))))
        ->toThrow(ClassifierUnavailableException::class)
        ->and(storedClassification($this))->toBeNull();
});

it('fails when the lead does not exist', function () {
    generateClassification($this, CallbackLeadClassifier::proposing(ClassificationProposalMother::create()), '00000000-0000-7000-8000-000000000099');
})->throws(LeadNotFoundError::class);

it('sends the lead to a person when the retries run out, unless it got classified meanwhile', function () {
    $record = new LeadClassificationFailureRecorder($this->classifications, $this->clock);

    $record(new LeadClassificationFailureRecorderRequest(LEAD_TO_CLASSIFY));
    expect(storedClassification($this))->toMatchArray(['status' => 'needs_review', 'review_reason' => 'La IA no respondió tras varios intentos.']);

    $this->classifications = new InMemoryLeadClassificationRepository;
    generateClassification($this, CallbackLeadClassifier::proposing(ClassificationProposalMother::create()));
    (new LeadClassificationFailureRecorder($this->classifications, $this->clock))(new LeadClassificationFailureRecorderRequest(LEAD_TO_CLASSIFY));

    expect(storedClassification($this)['status'])->toBe('classified');
});

it('records the human review', function () {
    generateClassification($this, CallbackLeadClassifier::proposing(ClassificationProposalMother::create(0.4)));

    (new LeadClassificationReviewer($this->classifications, $this->clock))(new LeadClassificationReviewerRequest(LEAD_TO_CLASSIFY, 'installations', 'low', 'ana@example.com'));

    expect(storedClassification($this))->toMatchArray(['status' => 'reviewed', 'category' => 'installations', 'urgency' => 'low', 'reviewed_by' => 'ana@example.com']);
});

it('rejects a review with an unknown category or for a lead without classification', function () {
    $review = new LeadClassificationReviewer($this->classifications, $this->clock);

    expect(fn () => $review(new LeadClassificationReviewerRequest(LEAD_TO_CLASSIFY, 'kitchen', 'low', 'ana@example.com')))->toThrow(LeadClassificationNotFoundError::class);

    generateClassification($this, CallbackLeadClassifier::proposing(ClassificationProposalMother::create()));

    expect(fn () => $review(new LeadClassificationReviewerRequest(LEAD_TO_CLASSIFY, 'roof', 'low', 'ana@example.com')))->toThrow(InvalidWorkCategoryError::class);
});

it('lists what needs review first, next to what the person wrote', function () {
    $second = '00000000-0000-7000-8000-000000000002';
    $this->leads->save(Lead::receive(new LeadId($second), LeadSubmissionMother::create(), $this->clock->now()));

    generateClassification($this, CallbackLeadClassifier::proposing(ClassificationProposalMother::create()));
    $this->clock->advance('+1 minute');
    generateClassification($this, CallbackLeadClassifier::proposing(ClassificationProposalMother::create(0.2)), $second);
    $this->clock->advance('+1 minute');

    $rows = (new LeadClassificationsSearcher($this->classifications, $this->leads))()->classifications;

    expect(array_column($rows, 'status'))->toBe(['needs_review', 'classified'])
        ->and($rows[1]['lead']['description'])->toBe('Fuga bajo la ducha');
});

final class SpyClassificationQueue implements ClassificationQueue
{
    /** @var list<string> */
    public array $enqueued = [];

    public function enqueue(LeadId $leadId): void
    {
        $this->enqueued[] = $leadId->value;
    }
}

it('queues the classification of new leads only', function (array $body, bool $queued) {
    $queue = new SpyClassificationQueue;

    (new ClassifyLeadOnLeadReceived($queue))(new LeadReceivedDomainEvent(LEAD_TO_CLASSIFY, new DateTimeImmutable, $body, repeated: $body['repeated']));

    expect($queue->enqueued)->toBe($queued ? [LEAD_TO_CLASSIFY] : []);
})->with([
    'new lead from the web' => [['source' => 'web-form', 'repeated' => false], true],
    'repeated contact' => [['source' => 'web-form', 'repeated' => true], false],
    'resilience demo lead' => [['source' => 'demo', 'repeated' => false], false],
]);
