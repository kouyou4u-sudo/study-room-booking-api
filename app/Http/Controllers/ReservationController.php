<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    /**
     * 座席状況を取得（予約済み座席の配列を返す）
     */
    public function getSeats(Request $request)
    {
        $date = $request->query('date');
        $timeSlot = $request->query('time_slot');

        if (!$date || !$timeSlot) {
            return response()->json(['error' => 'date and time_slot are required'], 400);
        }

        // 指定日・時間帯の予約済み座席を取得
        $bookedSeats = Reservation::where('date', $date)
            ->where('time_slot', $timeSlot)
            ->where('status', 'active')
            ->pluck('seat_number')
            ->toArray();

        return response()->json(['booked_seats' => $bookedSeats]);
    }

    /**
     * 予約を作成
     */
    public function store(Request $request)
    {
        // バリデーション
        $validated = $request->validate([
            'student_name' => 'required|string|max:255',
            'grade' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'date' => 'required|date',
            'time_slot' => 'required|string',
            'seat_number' => 'required|integer|between:1,20',
            'note' => 'nullable|string',
        ]);

        // 二重予約チェック
        $existing = Reservation::where('date', $validated['date'])
            ->where('time_slot', $validated['time_slot'])
            ->where('seat_number', $validated['seat_number'])
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return response()->json(['error' => 'This seat is already booked'], 409);
        }

        // 予約を作成
        $reservation = Reservation::create($validated);

        return response()->json($reservation, 201);
    }
}