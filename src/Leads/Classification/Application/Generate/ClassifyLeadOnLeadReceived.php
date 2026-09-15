<?php

namespace Reformas\Leads\Classification\Application\Generate;

use Reformas\Leads\Classification\Domain\ClassificationQueue;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadReceivedDomainEvent;
use Reformas\Shared\Domain\DomainEvent;
use Reformas\Shared\Domain\DomainEventSubscriber;

// Clasificar no forma parte de recibir: se encola y la recepción responde aunque la IA esté caída.
final readonly class ClassifyLeadOnLeadReceived implements DomainEventSubscriber
{
    // Los leads de la demo de resiliencia no gastan la cuota del modelo.
    private const SOURCES_NOT_CLASSIFIED = ['demo'];

    public function __construct(private ClassificationQueue $queue) {}

    public static function subscribedTo(): array
    {
        return [LeadReceivedDomainEvent::class];
    }

    public function __invoke(DomainEvent $event): void
    {
        $body = $event->body();

        // Un contacto repetido es la misma persona: ya se clasificó con el primero.
        if (($body['repeated'] ?? false) === true || in_array($body['source'] ?? null, self::SOURCES_NOT_CLASSIFIED, true)) {
            return;
        }

        $this->queue->enqueue(new LeadId($event->aggregateId));
    }
}
