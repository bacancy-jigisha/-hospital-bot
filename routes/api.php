<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Temporary — proves routing, CORS and the frontend's API base URL are wired
// correctly before any real endpoints exist. Removed once Phase 2 starts.
Route::get('/ping', function () {
    return response()->json(['data' => ['message' => 'pong']]);
});
