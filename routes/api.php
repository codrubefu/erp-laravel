<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccessControlController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/groups', [AccessControlController::class, 'groups'])->middleware('right:groups.view');
    Route::get('/rights', [AccessControlController::class, 'rights'])->middleware('right:rights.view');

    Route::get('/users', [UserController::class, 'index'])->middleware('right:users.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('right:users.manage');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('right:users.view');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('right:users.manage');
});
