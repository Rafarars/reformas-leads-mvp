<?php

namespace App\Http\Controllers\Api\V1\Leads;

use App\Http\Requests\Api\V1\Leads\StoreLeadRequest;
use Illuminate\Http\JsonResponse;
use Reformas\Leads\Lead\Application\Receive\LeadReceiver;
use Reformas\Leads\Lead\Application\Receive\LeadReceiverRequest;

final class LeadPostController
{
    public function __invoke(StoreLeadRequest $request, LeadReceiver $receive): JsonResponse
    {
        $data = $request->validated();

        $response = $receive(new LeadReceiverRequest(
            name: (string) $data['name'],
            phone: (string) $data['phone'],
            email: (string) $data['email'],
            renovationType: (string) $data['renovation_type'],
            zone: isset($data['zone']) ? (string) $data['zone'] : null,
            budget: isset($data['budget']) ? (int) $data['budget'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            source: (string) $request->attributes->get('api_client_source'),
        ));

        // 201 si es un lead nuevo; 200 si la persona ya existía y solo se registró un segundo contacto.
        return new JsonResponse($response->toArray(), $response->repeated ? 200 : 201);
    }
}
