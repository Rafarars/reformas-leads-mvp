<?php

use Reformas\Delivery\Outbox\Domain\RetryPolicy;
use Tests\Unit\Shared\FixedRandom;

function secondsUntil(DateTimeImmutable $from, DateTimeImmutable $to): int
{
    return $to->getTimestamp() - $from->getTimestamp();
}

it('doubles the delay after every failure', function () {
    $policy = new RetryPolicy(new FixedRandom(alwaysMax: true));
    $now = new DateTimeImmutable('2026-09-15T10:00:00+00:00');

    $delays = array_map(fn (int $attempt): int => secondsUntil($now, $policy->nextAttemptAt($attempt, $now)), [1, 2, 3, 4, 5]);

    expect($delays)->toBe([2, 4, 8, 16, 32]);
});

it('never waits more than the cap, even after hundreds of attempts', function () {
    $policy = new RetryPolicy(new FixedRandom(alwaysMax: true));
    $now = new DateTimeImmutable('2026-09-15T10:00:00+00:00');

    expect(secondsUntil($now, $policy->nextAttemptAt(9, $now)))->toBe(300)
        ->and(secondsUntil($now, $policy->nextAttemptAt(500, $now)))->toBe(300);
});

// La mitad fija evita reintentos inmediatos; la mitad aleatoria reparte a los que fallaron juntos.
it('keeps at least half of the delay fixed and randomizes the rest', function () {
    $now = new DateTimeImmutable('2026-09-15T10:00:00+00:00');

    $shortest = secondsUntil($now, (new RetryPolicy(new FixedRandom(alwaysMax: false)))->nextAttemptAt(5, $now));
    $longest = secondsUntil($now, (new RetryPolicy(new FixedRandom(alwaysMax: true)))->nextAttemptAt(5, $now));

    expect($shortest)->toBe(16)->and($longest)->toBe(32);
});

it('gives up only once the message is older than the limit', function () {
    $policy = new RetryPolicy(new FixedRandom, giveUpAfterSeconds: 86_400);
    $createdAt = new DateTimeImmutable('2026-09-15T10:00:00+00:00');

    expect($policy->shouldGiveUp($createdAt, new DateTimeImmutable('2026-09-16T09:59:59+00:00')))->toBeFalse()
        ->and($policy->shouldGiveUp($createdAt, new DateTimeImmutable('2026-09-16T10:00:00+00:00')))->toBeTrue();
});
