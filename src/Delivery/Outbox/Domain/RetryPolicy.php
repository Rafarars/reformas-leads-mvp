<?php

namespace Reformas\Delivery\Outbox\Domain;

use DateInterval;
use DateTimeImmutable;
use Reformas\Shared\Domain\Random;

final readonly class RetryPolicy
{
    // Por encima de este exponente el retraso ya está topado: evita desbordar 2^n tras cientos de intentos.
    private const MAX_EXPONENT = 20;

    public function __construct(
        private Random $random,
        private int $baseDelaySeconds = 2,
        private int $maxDelaySeconds = 300,
        private int $giveUpAfterSeconds = 86_400,
    ) {}

    // Backoff exponencial con jitter: la mitad del retraso es fija y la otra mitad aleatoria. Así, mil
    // mensajes que fallaron a la vez no reintentan en el mismo segundo cuando el servicio vuelve.
    public function nextAttemptAt(int $failedAttempts, DateTimeImmutable $now): DateTimeImmutable
    {
        $exponent = min(max(0, $failedAttempts - 1), self::MAX_EXPONENT);
        $delay = min($this->maxDelaySeconds, $this->baseDelaySeconds * (2 ** $exponent));
        $fixed = intdiv($delay, 2);
        $seconds = $fixed + $this->random->between(0, $delay - $fixed);

        return $now->add(new DateInterval('PT'.$seconds.'S'));
    }

    public function shouldGiveUp(DateTimeImmutable $createdAt, DateTimeImmutable $now): bool
    {
        return $now->getTimestamp() - $createdAt->getTimestamp() >= $this->giveUpAfterSeconds;
    }
}
