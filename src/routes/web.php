<?php

use App\Http\Controllers\AiController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AiController::class, 'index']);
Route::post('/chat', [AiController::class, 'chat']);
Route::get('/stream', [AiController::class, 'stream']); // Используем GET для EventSource
Route::post('/queue', [AiController::class, 'queue']);
Route::post('/broadcast', [AiController::class, 'broadcast']);
Route::post('/cancel', [AiController::class, 'cancel']);


