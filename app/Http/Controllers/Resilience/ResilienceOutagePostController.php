<?php

namespace App\Http\Controllers\Resilience;

use App\Services\FakeCrmClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ResilienceOutagePostController
{
    public function __invoke(Request $request, FakeCrmClient $crm): RedirectResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);

        if (! $crm->setOutage((bool) $validated['enabled'])) {
            return back()->withErrors(['crm' => 'No se pudo contactar con la API externa simulada.']);
        }

        return back();
    }
}
