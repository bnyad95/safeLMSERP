<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $fillable = [
        'type', 'filename', 'total_rows', 'successful', 'failed', 'errors', 'imported_by',
    ];

    protected $casts = [
        'errors' => 'array',
    ];

    public function importedBy()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
