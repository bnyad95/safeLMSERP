<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'uploaded_by',
        'type',
        'title',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'status',
        'notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
