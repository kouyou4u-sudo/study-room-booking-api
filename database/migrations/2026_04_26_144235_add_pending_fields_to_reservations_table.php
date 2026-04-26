<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * 既存の「同じ日付・時間帯・座席番号は1件だけ」というunique制約を外す。
         *
         * 今後は cancelled / expired の履歴も残すため、
         * DB側の単純なunique制約ではなく、
         * Controller側で active または期限内pending の予約だけを重複チェックする。
         */
        DB::statement('DROP INDEX IF EXISTS reservations_date_time_slot_seat_number_unique');

        Schema::table('reservations', function (Blueprint $table) {
            // 利用区分：在塾生 / 自習室会員 / 無料体験
            $table->string('usage_type')->nullable();

            // 利用者に見せる予約番号
            $table->string('reservation_code')->nullable()->unique();

            // メール確認用トークン
            $table->string('confirmation_token')->nullable()->unique();

            // 本予約確定日時
            $table->timestamp('confirmed_at')->nullable();

            // 仮予約の有効期限
            $table->timestamp('expires_at')->nullable();

            // キャンセル用トークン
            $table->string('cancel_token')->nullable()->unique();
        });
    }

    public function down(): void
    {
        /*
         * unique付きで作ったカラムには自動でindexが作られるので、
         * rollback時は先にindexを消してからカラムを消す。
         */
        DB::statement('DROP INDEX IF EXISTS reservations_reservation_code_unique');
        DB::statement('DROP INDEX IF EXISTS reservations_confirmation_token_unique');
        DB::statement('DROP INDEX IF EXISTS reservations_cancel_token_unique');

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'usage_type',
                'reservation_code',
                'confirmation_token',
                'confirmed_at',
                'expires_at',
                'cancel_token',
            ]);
        });

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS reservations_date_time_slot_seat_number_unique
             ON reservations (date, time_slot, seat_number)'
        );
    }
};