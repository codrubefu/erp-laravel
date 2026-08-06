<?php

use App\Reporting\Http\Controllers\Api\ReportController;
use App\Reporting\Http\Controllers\Api\SegmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/reports/financial', [ReportController::class, 'aggregate'])->middleware('right:reports.view');
    Route::post('/reports/financial/exports', [ReportController::class, 'export'])->middleware('right:reports.export');
    Route::get('/reports/exports/{export}', [ReportController::class, 'show'])->middleware('right:reports.export');
    Route::get('/reports/exports/{export}/download', [ReportController::class, 'download'])->middleware('right:reports.export');

    Route::get('/segments', [SegmentController::class, 'index'])->middleware('right:segments.view,segments.manage');
    Route::post('/segments', [SegmentController::class, 'store'])->middleware('right:segments.manage');
    Route::put('/segments/{segment}', [SegmentController::class, 'update'])->middleware('right:segments.manage');
    Route::delete('/segments/{segment}', [SegmentController::class, 'destroy'])->middleware('right:segments.manage');
    Route::get('/segments/{segment}/members', [SegmentController::class, 'members'])->middleware('right:segments.view,segments.manage');
});
