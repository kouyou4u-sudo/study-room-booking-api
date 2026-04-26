<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    /**
     * 座席状況を取得する
     *
     * active の本予約、または期限内 pending の仮予約を
     * 予約済み座席として返す。
     */
    public function getSeats(Request $request)
    {
        $date = $request->query('date');
        $timeSlot = $request->query('time_slot');

        if (!$date || !$timeSlot) {
            return response()->json([
                'error' => 'date and time_slot are required',
            ], 400);
        }

        $now = now();

        $bookedSeats = Reservation::where('date', $date)
            ->where('time_slot', $timeSlot)
            ->where(function ($query) use ($now) {
                $query->where('status', 'active')
                    ->orWhere(function ($pendingQuery) use ($now) {
                        $pendingQuery->where('status', 'pending')
                            ->where('expires_at', '>', $now);
                    });
            })
            ->pluck('seat_number')
            ->toArray();

        return response()->json([
            'booked_seats' => $bookedSeats,
        ]);
    }

    /**
     * 仮予約を作成する
     *
     * この時点では本予約ではなく status=pending として保存する。
     * メール確認リンクをクリックしたら status=active に変更する想定。
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_name' => 'required|string|max:255',
            'grade' => 'required|string|max:50',
            'usage_type' => 'required|string|in:在塾生,自習室会員,無料体験',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'date' => 'required|date',
            'time_slot' => 'required|string',
            'seat_number' => 'required|integer|between:1,20',
        ]);

        $now = now();

        /*
         * 二重予約チェック
         *
         * active の本予約、
         * または期限内 pending の仮予約がある場合は予約不可。
         *
         * cancelled / expired / 期限切れpending はブロックしない。
         */
        $existing = Reservation::where('date', $validated['date'])
            ->where('time_slot', $validated['time_slot'])
            ->where('seat_number', $validated['seat_number'])
            ->where(function ($query) use ($now) {
                $query->where('status', 'active')
                    ->orWhere(function ($pendingQuery) use ($now) {
                        $pendingQuery->where('status', 'pending')
                            ->where('expires_at', '>', $now);
                    });
            })
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'This seat is already booked',
                'message' => 'この座席はすでに予約されています。',
            ], 409);
        }

        $reservation = Reservation::create([
            'student_name' => $validated['student_name'],
            'grade' => $validated['grade'],
            'usage_type' => $validated['usage_type'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'date' => $validated['date'],
            'time_slot' => $validated['time_slot'],
            'seat_number' => $validated['seat_number'],
            'note' => null,
            'status' => 'pending',
            'reservation_code' => $this->generateReservationCode(),
            'confirmation_token' => Str::random(64),
            'confirmed_at' => null,
            'expires_at' => $now->copy()->addMinutes(30),
            'cancel_token' => Str::random(64),
        ]);

        /*
         * 次のステップでここに仮予約確認メール送信処理を追加する。
         */

        return response()->json([
            'message' => '仮予約を受け付けました。確認メール送信機能は次のステップで追加します。',
            'reservation' => $reservation,
        ], 201);
    }

    /**
     * 利用者に見せる予約番号を作る
     *
     * 例：SR-20260426-A7K3
     */
    private function generateReservationCode(): string
    {
        do {
            $code = 'SR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
        } while (Reservation::where('reservation_code', $code)->exists());

        return $code;
    }
}