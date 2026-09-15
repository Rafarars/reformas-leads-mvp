<?php

namespace App\Http\Controllers\Reception;

use Inertia\Inertia;
use Inertia\Response;
use Reformas\Leads\Lead\Domain\RenovationType;

final class ReceptionGetController
{
    public function __invoke(): Response
    {
        return Inertia::render('reception/create', [
            // Los valores salen del dominio: la interfaz no puede ofrecer un tipo que no existe.
            'renovationTypes' => array_column(RenovationType::cases(), 'value'),
        ]);
    }
}
