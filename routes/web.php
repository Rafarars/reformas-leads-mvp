<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Reception\ReceptionGetController;
use App\Http\Controllers\Reception\ReceptionLeadPostController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('reception', ReceptionGetController::class)->name('reception');
    Route::post('reception/leads', ReceptionLeadPostController::class)->name('reception.leads.store');
});

require __DIR__.'/settings.php';
