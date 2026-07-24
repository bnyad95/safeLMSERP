<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseMaterial extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    protected $fillable = [
        'course_id',
        'course_section_id',
        'title',
        'description',
        'file_path',
        'file_type',
        'visibility',
        'uploaded_by',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function courseSection()
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function getVisibilityOptions(): array
    {
        return [
            'draft' => 'Draft',
            'published' => 'Published',
        ];
    }

    public static function getFileTypeOptions(): array
    {
        return [
            'pdf' => 'PDF',
            'doc' => 'Document',
            'video' => 'Video',
            'image' => 'Image',
            'presentation' => 'Presentation',
            'other' => 'Other',
        ];
    }
}
