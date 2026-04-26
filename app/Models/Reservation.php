<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'student_name',
        'grade',
        'email',
        'phone',
        'date',
        'time_slot',
        'seat_number',
        'note',
        'status',
    ];
}