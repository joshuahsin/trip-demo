<?php

use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

Route::get('/trips', [TripController::class, 'index']);
Route::post('/trips', [TripController::class, 'store']);
