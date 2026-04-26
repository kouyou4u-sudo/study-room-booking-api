<?php

namespace App\Http\Controllers;

use App\Mail\ReservationConfirmationMail;
use App\Mail\ReservationConfirmedMail;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
     * 確認メール内のリンクをクリックしたら status=active に変更する。
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

        Mail::to($reservation->email)->send(
            new ReservationConfirmationMail($reservation)
        );

        return response()->json([
            'message' => '仮予約を受け付けました。確認メールを送信しました。',
            'reservation' => $reservation,
        ], 201);
    }

    /**
     * 仮予約確認リンクをクリックしたときに本予約へ変更する
     */
    public function confirm(string $token)
    {
        $reservation = Reservation::where('confirmation_token', $token)->first();

        if (!$reservation) {
            return response(
                '<h1>確認リンクが無効です</h1><p>予約情報が見つかりませんでした。</p>',
                404
            );
        }

        if ($reservation->status === 'active') {
            return response(
                '<h1>本予約はすでに確定済みです</h1><p>この予約はすでに本予約として確定しています。</p>',
                200
            );
        }

        if ($reservation->status === 'cancelled') {
            return response(
                '<h1>この予約はキャンセル済みです</h1><p>キャンセル済みの予約は確定できません。</p>',
                410
            );
        }

        if ($reservation->status === 'expired') {
            return response(
                '<h1>仮予約の有効期限が切れています</h1><p>お手数ですが、もう一度予約をお申し込みください。</p>',
                410
            );
        }

        if ($reservation->expires_at && $reservation->expires_at->isPast()) {
            $reservation->update([
                'status' => 'expired',
            ]);

            return response(
                '<h1>仮予約の有効期限が切れています</h1><p>お手数ですが、もう一度予約をお申し込みください。</p>',
                410
            );
        }

        $reservation->update([
            'status' => 'active',
            'confirmed_at' => now(),
        ]);

        /*
         * update直後の最新状態を取り直す。
         * confirmed_at や status を反映した状態で本予約確定メールを送るため。
         */
        $reservation->refresh();

        Mail::to($reservation->email)->send(
            new ReservationConfirmedMail($reservation)
        );

        return response(
            '<h1>本予約が確定しました</h1><p>自習室の予約が確定しました。ご利用をお待ちしております。</p>',
            200
        );
    }

    /**
     * キャンセルリンクをクリックしたときに予約をキャンセルする
     */
    public function cancel(string $token)
    {
        $reservation = Reservation::where('cancel_token', $token)->first();

        if (!$reservation) {
            return response(
                '<h1>キャンセルリンクが無効です</h1><p>予約情報が見つかりませんでした。</p>',
                404
            );
        }

        if ($reservation->status === 'cancelled') {
            return response(
                '<h1>この予約はすでにキャンセル済みです</h1><p>この予約はすでにキャンセルされています。</p>',
                200
            );
        }

        if ($reservation->status === 'expired') {
            return response(
                '<h1>この仮予約は期限切れです</h1><p>期限切れの仮予約はキャンセル操作の必要がありません。</p>',
                410
            );
        }

        $reservation->update([
            'status' => 'cancelled',
        ]);

        return response(
            '<h1>予約をキャンセルしました</h1><p>自習室の予約をキャンセルしました。</p>',
            200
        );
    }

    /**
     * 利用者に見せる予約番号を作る
     *
     * 例：SR-20260427-A7K3
     */
    private function generateReservationCode(): string
    {
        do {
            $code = 'SR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
        } while (Reservation::where('reservation_code', $code)->exists());

        return $code;
    }
}