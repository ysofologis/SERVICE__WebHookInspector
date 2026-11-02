<?php

use App\Http\Controllers\EndpointController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WebhookCaptureController;
use App\Http\Controllers\WebhookEventController;
use Illuminate\Support\Facades\Route;

// Public ingest: any method, CSRF-exempt (senders have no Laravel session).
// throttle:alias resolves via the 'webhook-capture' rate limiter.
Route::any('/hook/{token}', WebhookCaptureController::class)
    ->middleware(['throttle:webhook-capture'])
    ->name('webhooks.capture');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [EndpointController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/endpoints', [EndpointController::class, 'store'])->name('endpoints.store');
    Route::get('/endpoints/{endpoint}', [EndpointController::class, 'show'])->name('endpoints.show');
    Route::patch('/endpoints/{endpoint}', [EndpointController::class, 'update'])->name('endpoints.update');
    Route::delete('/endpoints/{endpoint}', [EndpointController::class, 'destroy'])->name('endpoints.destroy');

    Route::get('/endpoints/{endpoint}/events/{event}', [WebhookEventController::class, 'show'])
        ->name('events.show');
    Route::post('/endpoints/{endpoint}/events/{event}/replay', [WebhookEventController::class, 'replay'])
        ->name('events.replay');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
