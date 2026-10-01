<?php

use App\Services\FakeCrmClient;
use Illuminate\Support\Facades\Http;

it('sends the Authorization Bearer header when token is configured', function () {
    Http::fake([
        'http://crm.test/control' => Http::response(['outage' => false, 'rejected' => 0, 'duplicates' => 0, 'deliveries' => 0]),
        'http://crm.test/control/outage' => Http::response(['outage' => true, 'rejected' => 0, 'duplicates' => 0, 'deliveries' => 0]),
    ]);

    $client = new FakeCrmClient('http://crm.test', 'secret-crm-token');

    $state = $client->control();
    expect($state)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'http://crm.test/control'
        && $request->hasHeader('Authorization', 'Bearer secret-crm-token'));

    $updated = $client->setOutage(true);
    expect($updated)->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'http://crm.test/control/outage'
        && $request->hasHeader('Authorization', 'Bearer secret-crm-token')
        && $request['enabled'] === true);
});

it('does not send any Authorization header when token is not configured', function () {
    Http::fake([
        'http://crm.test/control' => Http::response(['outage' => false, 'rejected' => 0, 'duplicates' => 0, 'deliveries' => 0]),
        'http://crm.test/control/outage' => Http::response(['outage' => false, 'rejected' => 0, 'duplicates' => 0, 'deliveries' => 0]),
    ]);

    $client = new FakeCrmClient('http://crm.test', null);

    $state = $client->control();
    expect($state)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'http://crm.test/control'
        && ! $request->hasHeader('Authorization'));

    $updated = $client->setOutage(false);
    expect($updated)->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'http://crm.test/control/outage'
        && ! $request->hasHeader('Authorization'));
});

it('reads the token from configuration when resolved through the container', function () {
    Http::fake([
        'http://crm.test/control' => Http::response(['outage' => false, 'rejected' => 0, 'duplicates' => 0, 'deliveries' => 0]),
    ]);

    config([
        'services.fake_crm.url' => 'http://crm.test',
        'services.fake_crm.token' => 'container-token-1234',
    ]);

    $client = app(FakeCrmClient::class);
    $client->control();

    Http::assertSent(fn ($request) => $request->url() === 'http://crm.test/control'
        && $request->hasHeader('Authorization', 'Bearer container-token-1234'));
});

it('returns null on control and false on setOutage without sending requests when url is not configured', function () {
    $client = new FakeCrmClient(null, 'secret-crm-token');

    expect($client->control())->toBeNull();
    expect($client->setOutage(true))->toBeFalse();

    Http::assertNothingSent();
});

it('resolves safely without throwing when services.fake_crm.url is null', function () {
    config([
        'services.fake_crm.url' => null,
        'services.fake_crm.token' => null,
    ]);

    $client = app(FakeCrmClient::class);

    expect($client->control())->toBeNull();
    expect($client->setOutage(false))->toBeFalse();

    Http::assertNothingSent();
});
