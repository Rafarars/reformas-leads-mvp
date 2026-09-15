<?php

namespace App\Http\Controllers\Resilience;

use App\Services\FakeCrmClient;
use Inertia\Inertia;
use Inertia\Response;
use Reformas\Delivery\Outbox\Application\Search\DeliveryStatusSearcher;

final class ResilienceGetController
{
    public function __invoke(DeliveryStatusSearcher $search, FakeCrmClient $crm): Response
    {
        return Inertia::render('resilience/index', [
            'delivery' => $search()->toArray(),
            // null si el simulador no responde: el panel se sigue mostrando.
            'crm' => $crm->control(),
        ]);
    }
}
