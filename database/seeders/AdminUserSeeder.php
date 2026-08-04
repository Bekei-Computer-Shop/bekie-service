<?php

namespace Database\Seeders;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_admin' => true,
                'is_active' => true,
                'is_banned' => false,
            ]
        );

        if (Schema::hasTable('roles')) {
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);

            if (! $admin->hasRole('admin')) {
                $admin->assignRole($adminRole);
            }
        }

        if (Schema::hasTable('api_tokens')) {
            // Remove existing admin tokens for the user to avoid unique constraint collisions
            ApiToken::where('user_id', $admin->id)->where('scope', 'admin')->delete();

            $rawJti = 'test-admin-token-' . Str::random(40);
            $rawRefresh = 'test-admin-refresh-token-' . Str::random(40);

            $token = ApiToken::create([
                'user_id' => $admin->id,
                'token' => hash('sha256', $rawJti),
                'refresh_token' => hash('sha256', $rawRefresh),
                'expires_at' => now()->addMinutes(120),
                'refresh_expires_at' => now()->addDays(30),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Seeder',
                'revoked' => false,
                'scope' => 'admin',
            ]);

            // refresh model to pick up any generated attributes
            $token = $token->fresh();

            // expose raw tokens in console for testing convenience
            $this->command->info('Admin access_token (jti raw): '.$rawJti);
            $this->command->info('Admin refresh_token (raw): '.$rawRefresh);
        }
    }
}
