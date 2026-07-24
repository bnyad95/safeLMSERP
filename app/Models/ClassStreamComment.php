<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassStreamComment extends Model
{
    use SoftDeletes;

    protected $fillable = ['class_stream_post_id', 'user_id', 'body'];

    public function post()
    {
        return $this->belongsTo(ClassStreamPost::class, 'class_stream_post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
