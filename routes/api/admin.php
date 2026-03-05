<?php

use App\Http\Controllers\Api\Admin\KbImportController;
use App\Http\Controllers\Api\Admin\KbNodeController;
use Illuminate\Support\Facades\Route;

Route::prefix('kb')->group(function () {
    Route::post('import/excel', [KbImportController::class, 'importExcel']);

    Route::get('nodes', [KbNodeController::class, 'index']);
    Route::post('nodes', [KbNodeController::class, 'store']);
    Route::patch('nodes/{id}', [KbNodeController::class, 'update']);

    Route::post('nodes/{id}/fields', [KbNodeController::class, 'upsertFields']);
    Route::get('nodes/{id}/fields', [KbNodeController::class, 'fields']);

    Route::post('nodes/{id}/reindex', [KbNodeController::class, 'reindex']);
});
