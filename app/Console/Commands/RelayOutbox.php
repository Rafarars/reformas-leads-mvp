<?php

namespace App\Console\Commands;

use DateInterval;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Reformas\Delivery\Outbox\Domain\OutboxRepository;
use Reformas\Delivery\Outbox\Infrastructure\Queue\DeliverOutboxMessageJob;
use Reformas\Shared\Domain\Clock;

#[Signature('outbox:relay')]
#[Description('Vuelve a despachar los mensajes pendientes cuyo reintento lleva más de un minuto de retraso')]
class RelayOutbox extends Command
{
    // Margen para no pisar a los trabajos que la cola ya tiene programados a su hora.
    private const GRACE_SECONDS = 60;

    private const BATCH_SIZE = 100;

    public function handle(OutboxRepository $outbox, Clock $clock): int
    {
        // Red de seguridad: si el proceso murió entre guardar y despachar, o la cola perdió el trabajo,
        // el mensaje sigue en la base y este barrido lo recupera.
        $due = $outbox->searchDue($clock->now()->sub(new DateInterval('PT'.self::GRACE_SECONDS.'S')), self::BATCH_SIZE);

        foreach ($due as $message) {
            DeliverOutboxMessageJob::dispatch($message->id()->value);
        }

        $this->info(sprintf('%d mensajes vueltos a despachar.', count($due)));

        return self::SUCCESS;
    }
}
