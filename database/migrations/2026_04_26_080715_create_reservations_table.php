<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            // 利用者情報
            $table->string('student_name');               // 氏名
            $table->string('grade');                      // 学年
            $table->string('usage_type');                 // 利用区分
            $table->string('email');                      // メールアドレス
            $table->string('phone')->nullable();          // 電話番号（任意）

            // 予約情報
            $table->date('date');                         // 予約日
            $table->string('time_slot');                  // 時間帯（1コマ1レコード）
            $table->integer('seat_number');               // 座席番号
            $table->text('note')->nullable();             // 備考（任意）

            // 状態管理
            $table->string('status')->default('pending'); // pending / active / cancelled / expired

            // 申込み単位で共通に使う識別子
            $table->string('reservation_code')->nullable()->index();
            $table->string('confirmation_token')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('cancel_token')->nullable()->index();

            // 検索用インデックス
            $table->index(['date', 'time_slot', 'seat_number']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};