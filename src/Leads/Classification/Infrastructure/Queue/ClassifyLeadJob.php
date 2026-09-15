<?php

namespace Reformas\Leads\Classification\Infrastructure\Queue;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Reformas\Leads\Classification\Application\Fail\LeadClassificationFailureRecorder;
use Reformas\Leads\Classification\Application\Fail\LeadClassificationFailureRecorderRequest;
use Reformas\Leads\Classification\Application\Generate\LeadClassificationGenerator;
use Reformas\Leads\Classification\Application\Generate\LeadClassificationGeneratorRequest;
use Throwable;

final class ClassifyLeadJob implements ShouldQueue
{
    use Queueable;

    public const RATE_LIMITER = 'gemini';

    // Esperar a la cuota por minuto no cuenta como fallo: el límite real son las excepciones.
    public int $tries = 0;

    public int $maxExceptions = 5;

    public int $timeout = 60;

    public function __construct(public readonly string $leadId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        // El tier gratuito limita peticiones por minuto: sin esto una ráfaga de leads acaba en errores 429.
        return [new RateLimited(self::RATE_LIMITER)];
    }

    public function handle(LeadClassificationGenerator $generate): void
    {
        $generate(new LeadClassificationGeneratorRequest($this->leadId));
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
        app(LeadClassificationFailureRecorder::class)(new LeadClassificationFailureRecorderRequest($this->leadId));
    }
}
