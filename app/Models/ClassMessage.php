<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_section_id',
        'sender_id',
        'recipient_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'read_at',
    ];

    protected $casts = ['read_at' => 'datetime'];

    public function courseSection()
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
