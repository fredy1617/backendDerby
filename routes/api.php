<?php

use App\Http\Controllers\DerbysController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MatchesController;
use App\Http\Controllers\RolController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::middleware('auth:api')->post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->apiResource('/users', AuthController::class);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('derbys', DerbysController::class);
    Route::apiResource('derbys', DerbysController::class);
    Route::apiResource('matches', MatchesController::class);
    Route::apiResource('groups', GroupController::class);
    Route::apiResource('roles', RolController::class);
    Route::get('/generate-pdf-matches/{id}', [MatchesController::class, 'generatePDF']);
    Route::get('/generate-pdf-rol/{id}', [RolController::class, 'generatePDF']);
});

