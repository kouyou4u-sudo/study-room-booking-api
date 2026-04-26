<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReservationController;

Route::get('/reservations/seats', [ReservationController::class, 'getSeats']);
Route::post('/reservations', [ReservationController::class, 'store']);

// 仮予約メール内のリンクから本予約を確定する
Route::get('/reservations/confirm/{token}', [ReservationController::class, 'confirm']);