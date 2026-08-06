<?php

namespace App\Models;

use App\Traits\Loggable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Loggable, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'account_blocked_at',
        'account_blocked_by',
        'account_block_reason',
        'account_block_type',
        'university_id',
        'college_id',
        'department_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'must_change_password' => 'boolean',
            'account_blocked_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissionOverrides()
    {
        return $this->belongsToMany(Permission::class)->withPivot('effect')->withTimestamps();
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function accountBlocker()
    {
        return $this->belongsTo(User::class, 'account_blocked_by');
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function classStreamPosts()
    {
        return $this->hasMany(ClassStreamPost::class);
    }

    public function classStreamComments()
    {
        return $this->hasMany(ClassStreamComment::class);
    }

    public function classStreamReactions()
    {
        return $this->hasMany(ClassStreamReaction::class);
    }

    public function sentClassMessages()
    {
        return $this->hasMany(ClassMessage::class, 'sender_id');
    }

    public function receivedClassMessages()
    {
        return $this->hasMany(ClassMessage::class, 'recipient_id');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('super_administrator')) {
            return true;
        }

        $override = $this->permissionOverrides()
            ->where('name', $permission)
            ->first();

        if ($override) {
            return $override->pivot->effect === 'grant';
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }

    public function hasDirectPermissionGrant(string $permission): bool
    {
        return $this->permissionOverrides()
            ->where('name', $permission)
            ->wherePivot('effect', 'grant')
            ->exists();
    }

    public function hasAnyDirectPermissionGrant(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasDirectPermissionGrant($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->hasRole('super_administrator')) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
