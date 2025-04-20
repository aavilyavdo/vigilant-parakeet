2023_05_15_000000_create_files_table.php
Add the routes to your Laravel API routes file:

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/upload', [FileController::class, 'store']);
    Route::get('/files', [FileController::class, 'index']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']);
});

