<?php

use App\Http\Controllers\Api\V1\IntegrationApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/students', [IntegrationApiController::class, 'students'])->name('students.index');
    Route::get('/courses', [IntegrationApiController::class, 'courses'])->name('courses.index');
    Route::get('/marks', [IntegrationApiController::class, 'marks'])->name('marks.index');
});
