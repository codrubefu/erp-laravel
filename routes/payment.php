<?php

use App\Payments\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/payments', [PaymentController::class, 'index'])
        ->middleware('right:payments.view,payments.manage');
    Route::post('/payments', [PaymentController::class, 'store'])
        ->middleware('right:payments.create,payments.manage');
    Route::patch('/payments/{payment}/attach-model', [PaymentController::class, 'attachModel'])
        ->middleware('right:payments.update,payments.manage');
});
