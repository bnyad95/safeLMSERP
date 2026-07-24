<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassStreamPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_section_id',
        'user_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
    ];

    public function courseSection()
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(ClassStreamComment::class);
    }

    public function reactions()
    {
        return $this->hasMany(ClassStreamReaction::class);
    }
}
