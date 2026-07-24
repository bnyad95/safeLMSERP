<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimetableTimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'start_time',
        'end_time',
        'sort_order',
    ];

    public function timetables()
    {
        return $this->hasMany(Timetable::class, 'time_slot_id');
    }
}
