<?php

namespace App\Http\Controllers;

use App\Support\SystemStatus;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __invoke(SystemStatus $status): Response
    {
        return Inertia::render('dashboard', [
            'systemStatus' => $status->check(),
        ]);
    }
}
