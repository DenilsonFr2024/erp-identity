<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserApplication extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'user_application_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['user_id', 'application_id', 'granted_at'];

    protected $casts = [
        'granted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function application()
    {
        return $this->belongsTo(Application::class, 'application_id', 'application_id');
    }
}
