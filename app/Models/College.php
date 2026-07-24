<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class College extends Model
{
    protected $fillable = ['university_id', 'name', 'code'];

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }
}
