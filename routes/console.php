<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Red de seguridad del outbox: recupera los mensajes cuyo trabajo se perdió.
Schedule::command('outbox:relay')->everyMinute()->withoutOverlapping();
