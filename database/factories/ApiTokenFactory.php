<?php

namespace Database\Factories;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => Str::random(80),
            'refresh_token' => Str::random(80),
            'expires_at' => now()->addHours(24),
            'refresh_expires_at' => now()->addDays(30),
            'revoked' => false,
            'user_agent' => $this->faker->userAgent(),
            'ip_address' => $this->faker->ipv4(),
            'scope' => 'client',
        ];
    }
}
