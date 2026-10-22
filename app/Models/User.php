<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $primaryKey = 'user_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'username',
        'email',
        'password',
        'account_type',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'status' => 'string',
        'account_type' => 'string',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot(['user_role_id', 'company_id', 'branch_id'])
            ->withTimestamps();
    }

    public function applications()
    {
        return $this->belongsToMany(Application::class, 'user_applications', 'user_id', 'application_id')
            ->withPivot(['user_application_id', 'granted_at'])
            ->withTimestamps();
    }

    public function devices()
    {
        return $this->hasMany(Device::class, 'user_id', 'user_id');
    }

    public function hasAccessToApplication(string $appCode): bool
    {
        return $this->applications()->where('code', $appCode)->exists();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
