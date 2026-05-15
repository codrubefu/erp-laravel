<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccessControlController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/groups', [AccessControlController::class, 'groups'])->middleware('right:groups.view');
    Route::get('/rights', [AccessControlController::class, 'rights'])->middleware('right:rights.view');
});
