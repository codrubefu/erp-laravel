<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\RightController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/rights', [RightController::class, 'index'])->middleware('right:rights.view');
    Route::post('/rights', [RightController::class, 'store'])->middleware('right:rights.manage');
    Route::get('/rights/{right}', [RightController::class, 'show'])->middleware('right:rights.view');
    Route::put('/rights/{right}', [RightController::class, 'update'])->middleware('right:rights.manage');
    Route::patch('/rights/{right}', [RightController::class, 'update'])->middleware('right:rights.manage');
    Route::delete('/rights/{right}', [RightController::class, 'destroy'])->middleware('right:rights.manage');

    Route::get('/groups', [GroupController::class, 'index'])->middleware('right:groups.view');
    Route::post('/groups', [GroupController::class, 'store'])->middleware('right:groups.manage');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->middleware('right:groups.view');
    Route::put('/groups/{group}', [GroupController::class, 'update'])->middleware('right:groups.manage');
    Route::patch('/groups/{group}', [GroupController::class, 'update'])->middleware('right:groups.manage');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->middleware('right:groups.manage');

    Route::get('/users', [UserController::class, 'index'])->middleware('right:users.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('right:users.manage');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('right:users.view');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('right:users.manage');
});
