<?php

namespace App\Http\Controllers\Resilience;

use App\Support\DemoLeads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Reformas\Leads\Lead\Application\Receive\LeadReceiver;

final class ResilienceLeadsPostController
{
    private const MAX_LEADS = 50;

    public function __invoke(Request $request, LeadReceiver $receive): RedirectResponse
    {
        $validated = $request->validate(['count' => ['required', 'integer', 'min:1', 'max:'.self::MAX_LEADS]]);

        // El mismo caso de uso que la API y Recepción: cada lead deja su aviso en el outbox.
        for ($lead = 0; $lead < (int) $validated['count']; $lead++) {
            $receive(DemoLeads::request());
        }

        return back();
    }
}
