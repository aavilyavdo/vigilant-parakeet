<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/upload', [FileController::class, 'store']);
    Route::get('/files', [FileController::class, 'index']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']);
});
?>
