<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'application_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['code', 'name', 'status'];

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'application_id', 'application_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_applications', 'application_id', 'user_id')
            ->withPivot(['user_application_id', 'granted_at'])
            ->withTimestamps();
    }
}
