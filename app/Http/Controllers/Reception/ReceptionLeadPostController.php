<?php

namespace App\Http\Controllers\Reception;

use App\Http\Requests\Leads\ReceiveLeadRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Reformas\Leads\Lead\Application\Receive\LeadReceiver;
use Reformas\Leads\Lead\Application\Receive\LeadReceiverRequest;
use Reformas\Shared\Domain\InvalidArgumentError;

// Segunda puerta al mismo caso de uso que la API v1: el equipo carga leads desde el panel.
// La sesión autentica al usuario, así que no hace falta una API key en el navegador.
final class ReceptionLeadPostController
{
    private const SOURCE = 'reception';

    // Cada error de dominio se muestra debajo del campo que lo provocó.
    private const FIELD_BY_ERROR = [
        'invalid_lead_name' => 'name',
        'invalid_phone_number' => 'phone',
        'invalid_email_address' => 'email',
        'invalid_renovation_type' => 'renovation_type',
        'invalid_lead_zone' => 'zone',
        'invalid_budget' => 'budget',
        'invalid_lead_description' => 'description',
    ];

    public function __invoke(ReceiveLeadRequest $request, LeadReceiver $receive): RedirectResponse
    {
        $data = $request->validated();

        try {
            $response = $receive(new LeadReceiverRequest(
                name: (string) $data['name'],
                phone: (string) $data['phone'],
                email: (string) $data['email'],
                renovationType: (string) $data['renovation_type'],
                zone: isset($data['zone']) ? (string) $data['zone'] : null,
                budget: isset($data['budget']) ? (int) $data['budget'] : null,
                description: isset($data['description']) ? (string) $data['description'] : null,
                source: self::SOURCE,
            ));
        } catch (InvalidArgumentError $error) {
            throw ValidationException::withMessages([
                self::FIELD_BY_ERROR[$error->errorType()] ?? 'form' => $error->publicMessage(),
            ]);
        }

        return back()->with('receptionResult', $response->toArray());
    }
}
