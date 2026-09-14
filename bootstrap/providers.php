<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Reformas\Shared\Infrastructure\Laravel\ReformasServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ReformasServiceProvider::class,
];
