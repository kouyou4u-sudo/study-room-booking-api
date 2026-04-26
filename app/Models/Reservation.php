<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_name',
        'grade',
        'usage_type',
        'email',
        'phone',
        'date',
        'time_slot',
        'seat_number',
        'note',
        'status',
        'reservation_code',
        'confirmation_token',
        'confirmed_at',
        'expires_at',
        'cancel_token',
    ];

    protected $casts = [
        'date' => 'date',
        'confirmed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}