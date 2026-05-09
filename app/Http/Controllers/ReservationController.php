<?php

namespace App\Http\Controllers;

use App\Mail\ReservationConfirmationMail;
use App\Mail\ReservationConfirmedMail;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * 1回の申込みで複数時間帯を受け取れる。
     * DB上は 1コマ = 1レコード で保存するが、
     * reservation_code / confirmation_token / cancel_token は申込み単位で共通にする。
     * 確認メールは最後に1通だけ送る。
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
            'seat_number' => 'required|integer|between:1,20',
        ]);

        $timeSlots = $this->normalizeTimeSlots($request);

        if (count($timeSlots) === 0) {
            return response()->json([
                'error' => 'time_slot or time_slots is required',
                'message' => '時間帯が選択されていません。',
            ], 422);
        }

        $now = now();

        // 申込み単位で共通の識別子を先に作る
        $reservationCode = $this->generateReservationCode();
        $confirmationToken = Str::random(64);
        $cancelToken = Str::random(64);
        $expiresAt = $now->copy()->addMinutes(30);

        // 予約済みチェック
        $conflictedSlots = [];

        foreach ($timeSlots as $slot) {
            $existing = Reservation::where('date', $validated['date'])
                ->where('time_slot', $slot)
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
                $conflictedSlots[] = $slot;
            }
        }

        if (!empty($conflictedSlots)) {
            return response()->json([
                'error' => 'This seat is already booked',
                'message' => 'この座席はすでに予約されています。',
                'conflicted_time_slots' => $conflictedSlots,
            ], 409);
        }

        $createdReservations = DB::transaction(function () use (
            $validated,
            $timeSlots,
            $reservationCode,
            $confirmationToken,
            $cancelToken,
            $expiresAt
        ) {
            $reservations = collect();

            foreach ($timeSlots as $slot) {
                $reservations->push(
                    Reservation::create([
                        'student_name' => $validated['student_name'],
                        'grade' => $validated['grade'],
                        'usage_type' => $validated['usage_type'],
                        'email' => $validated['email'],
                        'phone' => $validated['phone'] ?? null,
                        'date' => $validated['date'],
                        'time_slot' => $slot,
                        'seat_number' => $validated['seat_number'],
                        'note' => null,
                        'status' => 'pending',
                        'reservation_code' => $reservationCode,
                        'confirmation_token' => $confirmationToken,
                        'confirmed_at' => null,
                        'expires_at' => $expiresAt,
                        'cancel_token' => $cancelToken,
                    ])
                );
            }

            return $reservations;
        });

        // メール表示用に、代表1件へ時間帯一覧を一時的にまとめて持たせる
        $mailReservation = $createdReservations->first();
        $mailReservation->time_slot = implode(' / ', $createdReservations->pluck('time_slot')->all());

        Mail::to($mailReservation->email)->send(
            new ReservationConfirmationMail($mailReservation)
        );

        return response()->json([
            'message' => '仮予約を受け付けました。確認メールを送信しました。',
            'reservation_code' => $reservationCode,
            'count' => $createdReservations->count(),
            'reservations' => $createdReservations,
        ], 201);
    }

    /**
     * 仮予約確認リンクをクリックしたときに本予約へ変更する
     *
     * 同じ confirmation_token を持つ予約をまとめて本予約化する。
     */
    public function confirm(string $token)
    {
        $reservations = Reservation::where('confirmation_token', $token)
            ->orderBy('date')
            ->orderBy('time_slot')
            ->get();

        if ($reservations->isEmpty()) {
            return response(
                '<h1>確認リンクが無効です</h1><p>予約情報が見つかりませんでした。</p>',
                404
            );
        }

        if ($reservations->every(fn ($reservation) => $reservation->status === 'active')) {
            return response(
                '<h1>本予約はすでに確定済みです</h1><p>この予約はすでに本予約として確定しています。</p>',
                200
            );
        }

        if ($reservations->contains(fn ($reservation) => $reservation->status === 'cancelled')
            && !$reservations->contains(fn ($reservation) => $reservation->status === 'pending')
        ) {
            return response(
                '<h1>この予約はキャンセル済みです</h1><p>キャンセル済みの予約は確定できません。</p>',
                410
            );
        }

        $now = now();
        $pendingReservations = $reservations->where('status', 'pending');

        if ($pendingReservations->isNotEmpty()) {
            $expired = $pendingReservations->contains(function ($reservation) use ($now) {
                return $reservation->expires_at && $reservation->expires_at->isPast();
            });

            if ($expired) {
                Reservation::where('confirmation_token', $token)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'expired',
                    ]);

                return response(
                    '<h1>仮予約の有効期限が切れています</h1><p>お手数ですが、もう一度予約をお申し込みください。</p>',
                    410
                );
            }
        }

        Reservation::where('confirmation_token', $token)
            ->where('status', 'pending')
            ->update([
                'status' => 'active',
                'confirmed_at' => $now,
            ]);

        $updatedReservations = Reservation::where('confirmation_token', $token)
            ->orderBy('date')
            ->orderBy('time_slot')
            ->get();

        $mailReservation = $updatedReservations->first();
        $mailReservation->time_slot = implode(' / ', $updatedReservations->pluck('time_slot')->all());

        Mail::to($mailReservation->email)->send(
            new ReservationConfirmedMail($mailReservation)
        );

        return response(
            '<h1>本予約が確定しました</h1><p>自習室の予約が確定しました。ご利用をお待ちしております。</p>',
            200
        );
    }

    /**
     * キャンセルリンクをクリックしたときに予約をキャンセルする
     *
     * 同じ cancel_token を持つ予約をまとめてキャンセルする。
     */
    public function cancel(string $token)
    {
        $reservations = Reservation::where('cancel_token', $token)
            ->orderBy('date')
            ->orderBy('time_slot')
            ->get();

        if ($reservations->isEmpty()) {
            return response(
                '<h1>キャンセルリンクが無効です</h1><p>予約情報が見つかりませんでした。</p>',
                404
            );
        }

        if ($reservations->every(fn ($reservation) => $reservation->status === 'cancelled')) {
            return response(
                '<h1>この予約はすでにキャンセル済みです</h1><p>この予約はすでにキャンセルされています。</p>',
                200
            );
        }

        $activeOrPendingExists = $reservations->contains(function ($reservation) {
            return in_array($reservation->status, ['active', 'pending'], true);
        });

        if (!$activeOrPendingExists) {
            return response(
                '<h1>この仮予約は期限切れです</h1><p>期限切れの仮予約はキャンセル操作の必要がありません。</p>',
                410
            );
        }

        Reservation::where('cancel_token', $token)
            ->whereIn('status', ['active', 'pending'])
            ->update([
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

    /**
     * time_slot / time_slots の両方を受けられるように正規化する
     */
    private function normalizeTimeSlots(Request $request): array
    {
        $timeSlots = $request->input('time_slots');

        if (is_string($timeSlots)) {
            $timeSlots = [$timeSlots];
        }

        if (empty($timeSlots) && $request->filled('time_slot')) {
            $timeSlots = [$request->input('time_slot')];
        }

        if (!is_array($timeSlots)) {
            return [];
        }

        return collect($timeSlots)
            ->map(fn ($slot) => is_string($slot) ? trim($slot) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}