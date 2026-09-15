<?php

namespace Reformas\Delivery\Outbox\Application\Deliver;

enum DeliveryOutcomeType: string
{
    case Delivered = 'delivered';
    case RetryScheduled = 'retry_scheduled';
    case GaveUp = 'gave_up';
    case CircuitOpen = 'circuit_open';
    case Skipped = 'skipped';
}
