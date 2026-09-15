<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Delivery\Outbox\Infrastructure\Queue\DeliverOutboxMessageJob;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.fake_crm.url' => 'http://crm.test']);
    $this->user = User::factory()->create();
});

function savedOutboxMessage(int $number, bool $dead = false): OutboxMessage
{
    $message = OutboxMessage::create(
        new OutboxMessageId(sprintf('00000000-0000-7000-8000-%012d', $number)),
        'lead.received',
        '00000000-0000-7000-8000-0000000000aa',
        ['name' => 'Ana García', 'repeated' => false],
        new DateTimeImmutable,
    );

    if ($dead) {
        $message->markDead('El destino respondió HTTP 500');
    }

    app(OutboxRepository::class)->save($message);

    return $message;
}

it('sends guests to the login page', function () {
    $this->get(route('resilience'))->assertRedirect(route('login'));
    $this->post(route('resilience.leads'), ['count' => 3])->assertRedirect(route('login'));
});

it('shows the outbox and the simulated CRM', function () {
    Http::fake(['crm.test/control' => Http::response(['outage' => true, 'rejected' => 3, 'duplicates' => 0, 'deliveries' => 7])]);
    savedOutboxMessage(1);
    savedOutboxMessage(2, dead: true);

    $this->actingAs($this->user)
        ->get(route('resilience'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resilience/index')
            ->where('crm.outage', true)
            ->where('crm.rejected', 3)
            ->where('delivery.counts.pending', 1)
            ->where('delivery.counts.dead', 1)
            ->where('delivery.circuit.open', false)
            ->has('delivery.messages', 2));
});

it('still shows the panel when the simulated CRM does not answer', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->actingAs($this->user)
        ->get(route('resilience'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('crm', null));
});

it('switches the simulated outage on', function () {
    Http::fake(['crm.test/control/outage' => Http::response(['outage' => true])]);

    $this->actingAs($this->user)
        ->from(route('resilience'))
        ->post(route('resilience.outage'), ['enabled' => true])
        ->assertRedirect(route('resilience'));

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/control/outage') && $request['enabled'] === true);
});

it('reports that the simulated CRM could not be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->actingAs($this->user)
        ->from(route('resilience'))
        ->post(route('resilience.outage'), ['enabled' => true])
        ->assertSessionHasErrors('crm');
});

it('injects fictional leads whose notices land in the outbox', function () {
    $this->actingAs($this->user)
        ->from(route('resilience'))
        ->post(route('resilience.leads'), ['count' => 3])
        ->assertRedirect(route('resilience'));

    expect(DB::table('leads')->where('source', 'demo')->count())->toBe(3)
        ->and(DB::table('outbox_messages')->count())->toBe(3);
    Queue::assertPushed(DeliverOutboxMessageJob::class, 3);
});

it('limits how many leads can be injected at once', function () {
    $this->actingAs($this->user)
        ->post(route('resilience.leads'), ['count' => 51])
        ->assertSessionHasErrors('count');

    expect(DB::table('leads')->count())->toBe(0);
});

it('replays a message that was set aside', function () {
    $message = savedOutboxMessage(1, dead: true);

    $this->actingAs($this->user)
        ->from(route('resilience'))
        ->post(route('resilience.replay', ['message' => $message->id()->value]))
        ->assertRedirect(route('resilience'))
        ->assertSessionHasNoErrors();

    expect(app(OutboxRepository::class)->search($message->id())?->toPrimitives()['status'])->toBe('pending');
    Queue::assertPushed(DeliverOutboxMessageJob::class, fn (DeliverOutboxMessageJob $job): bool => $job->messageId === $message->id()->value);
});

it('refuses to replay a message that is not set aside', function () {
    $message = savedOutboxMessage(1);

    $this->actingAs($this->user)
        ->from(route('resilience'))
        ->post(route('resilience.replay', ['message' => $message->id()->value]))
        ->assertSessionHasErrors(['replay' => 'Solo se pueden reintentar los mensajes apartados.']);

    Queue::assertNothingPushed();
});
