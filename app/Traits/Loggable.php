<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait Loggable
{
    protected static function bootLoggable()
    {
        static::created(function ($model) {
            self::logActivity($model, 'created');
        });

        static::updated(function ($model) {
            self::logActivity($model, 'updated', $model->getDirty());
        });

        static::deleted(function ($model) {
            self::logActivity($model, 'deleted');
        });
    }

    protected static function logActivity($model, $description, $changes = [])
    {
        ActivityLog::create([
            'log_name' => get_class($model),
            'description' => $description,
            'subject_type' => get_class($model),
            'subject_id' => $model->id,
            'causer_type' => Auth::check() ? Auth::user()::class : null,
            'causer_id' => Auth::id(),
            'properties' => [
                'attributes' => $model->getAttributes(),
                'changes' => $changes,
            ],
        ]);
    }
}
