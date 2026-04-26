<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReservationController;

Route::get('/reservations/seats', [ReservationController::class, 'getSeats']);
Route::post('/reservations', [ReservationController::class, 'store']);