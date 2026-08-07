<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'university_id',
        'name',
        'building',
        'capacity',
        'type',
        'status',
        'notes',
    ];

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function timetables()
    {
        return $this->hasMany(Timetable::class);
    }
}
