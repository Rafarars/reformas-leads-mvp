<?php

namespace Reformas\Delivery\Outbox\Infrastructure\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Reformas\Delivery\Outbox\Domain\DeliveryResult;
use Reformas\Delivery\Outbox\Domain\MessageDeliverer;
use Reformas\Delivery\Outbox\Domain\OutboxMessage;

final readonly class WebhookMessageDeliverer implements MessageDeliverer
{
    public function __construct(
        private ?string $url,
        private int $timeoutSeconds,
    ) {}

    public function deliver(OutboxMessage $message): DeliveryResult
    {
        if ($this->url === null || $this->url === '') {
            return DeliveryResult::failed('No hay un destino de entrega configurado (DELIVERY_WEBHOOK_URL).');
        }

        $primitives = $message->toPrimitives();

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                // El id del mensaje como clave: si un reintento llega dos veces, el receptor lo descarta.
                ->withHeaders(['Idempotency-Key' => $primitives['id']])
                ->post($this->url, [
                    'id' => $primitives['id'],
                    'event' => $primitives['event_name'],
                    'aggregate_id' => $primitives['aggregate_id'],
                    'occurred_at' => $primitives['created_at'],
                    'data' => $primitives['payload'],
                ]);
        } catch (ConnectionException $exception) {
            return DeliveryResult::failed('Sin conexión con el destino: '.$exception->getMessage());
        }

        return $response->successful()
            ? DeliveryResult::succeeded()
            : DeliveryResult::failed('El destino respondió HTTP '.$response->status());
    }
}
