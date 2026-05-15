<?php

use App\Users\Http\Controllers\Api\AuthController;
use App\Users\Http\Controllers\Api\GroupController;
use App\Users\Http\Controllers\Api\LocationController;
use App\Users\Http\Controllers\Api\RightController;
use App\Users\Http\Controllers\Api\UserController;
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

    Route::get('/locations', [LocationController::class, 'index'])->middleware('right:locations.view');
    Route::post('/locations', [LocationController::class, 'store'])->middleware('right:locations.manage');
    Route::get('/locations/{location}', [LocationController::class, 'show'])->middleware('right:locations.view');
    Route::put('/locations/{location}', [LocationController::class, 'update'])->middleware('right:locations.manage');
    Route::patch('/locations/{location}', [LocationController::class, 'update'])->middleware('right:locations.manage');
    Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->middleware('right:locations.manage');

    Route::get('/administrators', [UserController::class, 'administrators'])->middleware('right:users.view');
    Route::get('/clients', [UserController::class, 'clients'])->middleware('right:users.view');
    Route::get('/users', [UserController::class, 'index'])->middleware('right:users.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('right:users.manage');
    Route::patch('/users/subscription/{user}', [UserController::class, 'syncSubscriptions'])->middleware('right:users.manage');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('right:users.view');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('right:users.manage');

    Route::post('/clients', [UserController::class, 'store'])->middleware('right:users.manage');
    Route::get('/clients/{user}', [UserController::class, 'show'])->middleware('right:users.view');
    Route::put('/clients/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::patch('/clients/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::delete('/clients/{user}', [UserController::class, 'destroy'])->middleware('right:users.manage');

    Route::post('/administrators', [UserController::class, 'store'])->middleware('right:users.manage');
    Route::get('/administrators/{user}', [UserController::class, 'show'])->middleware('right:users.view');
    Route::put('/administrators/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::patch('/administrators/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::delete('/administrators/{user}', [UserController::class, 'destroy'])->middleware('right:users.manage');
});
