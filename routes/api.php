<?php

use App\Http\Controllers\DerbysController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MatchesController;
use App\Http\Controllers\RolController;
use Illuminate\Support\Facades\Route;


Route::apiResource('derbys', DerbysController::class);
Route::apiResource('matches', MatchesController::class);
Route::apiResource('groups', GroupController::class);
Route::apiResource('roles', RolController::class);
Route::get('/generate-pdf-matches/{id}', [MatchesController::class, 'generatePDF']);
Route::get('/generate-pdf-rol/{id}', [RolController::class, 'generatePDF']);
