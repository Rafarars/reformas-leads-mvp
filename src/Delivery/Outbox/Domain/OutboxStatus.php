<?php

namespace Reformas\Delivery\Outbox\Domain;

enum OutboxStatus: string
{
    // Guardado y esperando entregarse, o reintentándose.
    case Pending = 'pending';
    case Delivered = 'delivered';
    // Agotó sus reintentos: apartado con su último error, nunca descartado.
    case Dead = 'dead';
}
