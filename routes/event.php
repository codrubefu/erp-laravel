<?php

use App\Events\Http\Controllers\Api\EventController;
use App\Events\Http\Controllers\Api\EventOccurrenceController;
use App\Events\Http\Controllers\Api\EventParticipantController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/events', [EventController::class, 'index'])
        ->middleware('right:events.view,events.manage');
    Route::post('/events', [EventController::class, 'store'])
        ->middleware('right:events.manage');
    Route::get('/events/{event}', [EventController::class, 'show'])
        ->middleware('right:events.view,events.manage');
    Route::put('/events/{event}', [EventController::class, 'update'])
        ->middleware('right:events.manage');
    Route::patch('/events/{event}', [EventController::class, 'update'])
        ->middleware('right:events.manage');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])
        ->middleware('right:events.manage');

    Route::get('/events/{event}/occurrences', [EventOccurrenceController::class, 'index'])
        ->middleware('right:events.view,events.manage');
    Route::get('/event-occurrences/{occurrence}', [EventOccurrenceController::class, 'show'])
        ->middleware('right:events.view,events.manage');

    Route::get('/event-occurrences/{occurrence}/participants', [EventParticipantController::class, 'index'])
        ->middleware('right:event_participants.view,event_participants.manage');
    Route::post('/event-occurrences/{occurrence}/participants', [EventParticipantController::class, 'store'])
        ->middleware('right:event_participants.manage');
    Route::delete('/event-occurrences/{occurrence}/participants/{user}', [EventParticipantController::class, 'destroy'])
        ->middleware('right:event_participants.manage');
});
