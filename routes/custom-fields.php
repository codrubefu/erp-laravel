<?php

use App\CustomFields\Http\Controllers\Api\CustomFieldController;
use App\CustomFields\Http\Controllers\Api\CustomFieldValueController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/custom-fields', [CustomFieldController::class, 'index']);
    Route::post('/custom-fields', [CustomFieldController::class, 'store']);
    Route::put('/custom-fields/{customField}', [CustomFieldController::class, 'update']);
    Route::patch('/custom-fields/{customField}', [CustomFieldController::class, 'update']);
    Route::delete('/custom-fields/{customField}', [CustomFieldController::class, 'destroy']);

    Route::get('/{entityType}/{entityId}/custom-field-values', [CustomFieldValueController::class, 'show'])
        ->whereNumber('entityId');
    Route::post('/{entityType}/{entityId}/custom-field-values', [CustomFieldValueController::class, 'store'])
        ->whereNumber('entityId');
});
