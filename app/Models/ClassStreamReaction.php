<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassStreamReaction extends Model
{
    protected $fillable = ['class_stream_post_id', 'user_id', 'type'];

    public function post()
    {
        return $this->belongsTo(ClassStreamPost::class, 'class_stream_post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
