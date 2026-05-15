<?php

use App\Subscription\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])
        ->middleware('right:subscriptions.view,subscriptions.manage');
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])
        ->middleware('right:subscriptions.create,subscriptions.manage');
    Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])
        ->middleware('right:subscriptions.view,subscriptions.manage');
    Route::put('/subscriptions/{subscription}', [SubscriptionController::class, 'update'])
        ->middleware('right:subscriptions.update,subscriptions.manage');
    Route::patch('/subscriptions/{subscription}', [SubscriptionController::class, 'update'])
        ->middleware('right:subscriptions.update,subscriptions.manage');
    Route::delete('/subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])
        ->middleware('right:subscriptions.delete,subscriptions.manage');
    Route::post('/subscriptions/{subscription}/restore', [SubscriptionController::class, 'restore'])
        ->middleware('right:subscriptions.restore,subscriptions.manage');
    Route::patch('/subscriptions/{subscription}/toggle-active', [SubscriptionController::class, 'toggleActive'])
        ->middleware('right:subscriptions.update,subscriptions.manage');
});
