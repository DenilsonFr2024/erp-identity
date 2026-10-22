<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username' => Str::random(10),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('Password-Segura-2026'),
            'account_type' => 'internal',
            'status' => 'active',
        ];
    }
}
