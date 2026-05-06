<?php

use App\Interface\Http\Ai\Controller\BroadcastChatController;
use App\Interface\Http\Ai\Controller\CancelChatController;
use App\Interface\Http\Ai\Controller\IndexController;
use App\Interface\Http\Ai\Controller\QueueChatController;
use App\Interface\Http\Ai\Controller\StartChatController;
use App\Interface\Http\Ai\Controller\StreamChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexController::class);
Route::post('/chat', StartChatController::class);
Route::get('/stream', StreamChatController::class);
Route::post('/queue', QueueChatController::class);
Route::post('/cancel', CancelChatController::class);
Route::post('/broadcast', BroadcastChatController::class);
