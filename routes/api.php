<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DocumentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/documents', [DocumentController::class, 'index']);
Route::post('/documents', [DocumentController::class, 'store']);

Route::post('/chat', [ChatController::class, 'send'])->middleware('throttle:20,1');
Route::get('/chat/{sessionId}', [ChatController::class, 'history']);
