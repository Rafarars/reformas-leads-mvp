<?php

use App\Http\Controllers\Api\V1\Leads\LeadAttachmentPostController;
use App\Http\Controllers\Api\V1\Leads\LeadPostController;
use App\Http\Controllers\Api\V1\Telegram\TelegramLinkPostController;
use Illuminate\Support\Facades\Route;

// El orden importa: el límite y la idempotencia necesitan saber qué cliente llama.
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['api.client', 'throttle:api-clients', 'idempotent'])
    ->group(function () {
        Route::post('leads', LeadPostController::class)->name('leads.store');
        Route::post('leads/{lead}/attachments', LeadAttachmentPostController::class)->name('leads.attachments.store');
        Route::post('telegram/link', TelegramLinkPostController::class)->name('telegram.link.store');
    });
