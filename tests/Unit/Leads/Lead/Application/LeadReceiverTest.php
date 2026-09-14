<?php

use Reformas\Leads\Lead\Application\Receive\LeadReceiver;
use Reformas\Leads\Lead\Domain\InvalidPhoneNumberError;
use Reformas\Leads\Lead\Domain\LeadHistoryEntry;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Infrastructure\Persistence\InMemory\InMemoryLeadRepository;
use Tests\Unit\Leads\Lead\LeadReceiverRequestMother;
use Tests\Unit\Shared\FixedClock;
use Tests\Unit\Shared\SequentialUuidGenerator;
use Tests\Unit\Shared\SpyTransaction;

beforeEach(function () {
    $this->leads = new InMemoryLeadRepository;
    $this->transaction = new SpyTransaction;
    $this->clock = new FixedClock;
    $this->receive = new LeadReceiver($this->leads, $this->transaction, $this->clock, new SequentialUuidGenerator);
});

function recordedHistory(InMemoryLeadRepository $leads, string $id): array
{
    return array_map(static fn (LeadHistoryEntry $entry): string => $entry->type->value, $leads->history(new LeadId($id)));
}

it('stores a new lead and answers that it was received', function () {
    $response = ($this->receive)(LeadReceiverRequestMother::create());

    expect($response->toArray())->toBe([
        'id' => '00000000-0000-7000-8000-000000000001',
        'status' => 'received',
        'contact_count' => 1,
        'repeated' => false,
    ])
        ->and($this->leads->search(new LeadId($response->id)))->not->toBeNull()
        ->and($this->transaction->runs)->toBe(1);
});

it('treats the same phone as the same person even when written differently', function () {
    $first = ($this->receive)(LeadReceiverRequestMother::create(phone: '600111222'));
    $this->clock->advance('+1 hour');
    $second = ($this->receive)(LeadReceiverRequestMother::create(phone: '+34 600 111 222'));

    expect($second->id)->toBe($first->id)
        ->and($second->repeated)->toBeTrue()
        ->and($second->contactCount)->toBe(2)
        ->and(recordedHistory($this->leads, $first->id))->toBe(['received', 'repeated_contact']);
});

it('treats the same email as the same person', function () {
    $first = ($this->receive)(LeadReceiverRequestMother::create(email: 'ana@example.com'));
    $second = ($this->receive)(LeadReceiverRequestMother::create(email: 'ANA@example.com'));

    expect($second->id)->toBe($first->id)->and($second->repeated)->toBeTrue();
});

it('keeps what was known on a repeated contact and completes what was missing', function () {
    $first = ($this->receive)(LeadReceiverRequestMother::withoutOptionalFields(phone: '600111222'));
    ($this->receive)(LeadReceiverRequestMother::create(phone: '600111222', zone: 'Madrid', budget: 20_000));
    ($this->receive)(LeadReceiverRequestMother::create(phone: '600111222', zone: 'Valencia', budget: 90_000));

    $stored = $this->leads->search(new LeadId($first->id))?->toPrimitives();

    expect($stored['zone'])->toBe('Madrid')
        ->and($stored['budget'])->toBe(20_000)
        ->and($stored['contact_count'])->toBe(3);
});

it('does not open a transaction when the data is invalid', function () {
    try {
        ($this->receive)(LeadReceiverRequestMother::create(phone: 'not a phone'));
    } catch (InvalidPhoneNumberError) {
        expect($this->transaction->runs)->toBe(0);

        return;
    }

    throw new RuntimeException('Se esperaba InvalidPhoneNumberError');
});
