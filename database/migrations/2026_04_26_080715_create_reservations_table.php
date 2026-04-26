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
            $table->string('student_name');       // 氏名
            $table->string('grade');               // 学年
            $table->string('email');               // メールアドレス
            $table->string('phone')->nullable();    // 電話番号（任意）
            $table->date('date');                  // 予約日
            $table->string('time_slot');           // 時間帯（例："13:00〜13:55"）
            $table->integer('seat_number');        // 座席番号（1〜20）
            $table->text('note')->nullable();      // 備考（任意）
            $table->string('status')->default('active'); // ステータス
            $table->unique(['date', 'time_slot', 'seat_number']); // 二重予約防止
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