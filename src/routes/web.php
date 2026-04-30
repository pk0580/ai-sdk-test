<?php

use App\Interface\Http\Controller\AiController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AiController::class, 'index']);
Route::post('/chat', [AiController::class, 'chat']);
Route::get('/stream', [AiController::class, 'stream']);
Route::post('/queue', [AiController::class, 'queue']);
Route::post('/cancel', [AiController::class, 'cancel']);
Route::post('/broadcast', [AiController::class, 'broadcast']);
