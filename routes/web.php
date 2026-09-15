<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Reception\ReceptionGetController;
use App\Http\Controllers\Reception\ReceptionLeadPostController;
use App\Http\Controllers\Resilience\ResilienceGetController;
use App\Http\Controllers\Resilience\ResilienceLeadsPostController;
use App\Http\Controllers\Resilience\ResilienceOutagePostController;
use App\Http\Controllers\Resilience\ResilienceReplayPostController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('reception', ReceptionGetController::class)->name('reception');
    Route::post('reception/leads', ReceptionLeadPostController::class)->name('reception.leads.store');

    Route::get('resilience', ResilienceGetController::class)->name('resilience');
    Route::post('resilience/outage', ResilienceOutagePostController::class)->name('resilience.outage');
    Route::post('resilience/leads', ResilienceLeadsPostController::class)->name('resilience.leads');
    Route::post('resilience/outbox/{message}/replay', ResilienceReplayPostController::class)->name('resilience.replay');
});

require __DIR__.'/settings.php';
