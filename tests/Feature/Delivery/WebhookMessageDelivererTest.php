<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;
use Reformas\Delivery\Outbox\Domain\OutboxMessageId;
use Reformas\Delivery\Outbox\Infrastructure\Http\WebhookMessageDeliverer;

function webhookMessage(): OutboxMessage
{
    return OutboxMessage::create(
        new OutboxMessageId('00000000-0000-7000-8000-000000000001'),
        'lead.received',
        '00000000-0000-7000-8000-0000000000aa',
        ['name' => 'Ana García', 'repeated' => false],
        new DateTimeImmutable('2026-09-15T10:00:00+00:00'),
    );
}

it('delivers the event with the message id as idempotency key', function () {
    Http::fake(['crm.test/*' => Http::response(['ok' => true], 202)]);

    $result = (new WebhookMessageDeliverer('https://crm.test/webhooks/leads', 5))->deliver(webhookMessage());

    expect($result->succeeded)->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://crm.test/webhooks/leads'
        && $request->hasHeader('Idempotency-Key', '00000000-0000-7000-8000-000000000001')
        && $request['event'] === 'lead.received'
        && $request['aggregate_id'] === '00000000-0000-7000-8000-0000000000aa'
        && $request['data']['name'] === 'Ana García');
});

it('reports an error response as a failed delivery', function () {
    Http::fake(['crm.test/*' => Http::response(null, 503)]);

    $result = (new WebhookMessageDeliverer('https://crm.test/webhooks/leads', 5))->deliver(webhookMessage());

    expect($result->succeeded)->toBeFalse()
        ->and($result->failureReason)->toBe('El destino respondió HTTP 503');
});

it('reports a connection problem as a failed delivery instead of throwing', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $result = (new WebhookMessageDeliverer('https://crm.test/webhooks/leads', 5))->deliver(webhookMessage());

    expect($result->succeeded)->toBeFalse()
        ->and($result->failureReason)->toContain('Sin conexión con el destino');
});

it('fails without calling anyone when no destination is configured', function () {
    Http::fake();

    $result = (new WebhookMessageDeliverer(null, 5))->deliver(webhookMessage());

    expect($result->succeeded)->toBeFalse();
    Http::assertNothingSent();
});
