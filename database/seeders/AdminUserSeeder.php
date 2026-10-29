<?php

namespace Database\Seeders;

use App\Models\ApiToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Used only when SEED_ADMIN_PASSWORD is unset in the local environment. */
    private const LOCAL_FALLBACK_PASSWORD = 'password';

    public function run(): void
    {
        // Resolved before anything is written, so a missing password in a
        // shared environment leaves every account as it was.
        $password = Hash::make($this->password());

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => $password,
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

            $rawJti = 'test-admin-token-'.Str::random(40);
            $rawRefresh = 'test-admin-refresh-token-'.Str::random(40);

            $token = ApiToken::create([
                'user_id' => $admin->id,
                'token' => hash('sha256', $rawJti),
                'refresh_token' => hash('sha256', $rawRefresh),
                'expires_at' => now()->addDays(60),
                'refresh_expires_at' => now()->addDays(60),
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

        // A staff-role login for the portal, so the scoped role can be signed
        // into without hand-building a user. Same shape as the admin above,
        // minus the API token: that fixture exists for scripted admin calls,
        // while staff goes through /admin/auth/login like a real user.
        // is_admin is what authenticateAdmin() gates on -- without it the
        // login is refused whatever role the user holds.
        $staff = User::updateOrCreate(
            ['email' => 'staff@gmail.com'],
            [
                'first_name' => 'Staff',
                'last_name' => 'User',
                'password' => $password,
                'role' => 'staff',
                'is_admin' => true,
                'is_active' => true,
                'is_banned' => false,
            ]
        );

        if (Schema::hasTable('roles')) {
            $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'api']);

            if (! $staff->hasRole('staff')) {
                $staff->assignRole($staffRole);
            }
        }

        // A manager-role login for the portal, same shape as staff above.
        $manager = User::updateOrCreate(
            ['email' => 'manager@example.com'],
            [
                'first_name' => 'Manager',
                'last_name' => 'User',
                'password' => $password,
                'role' => 'manager',
                'is_admin' => true,
                'is_active' => true,
                'is_banned' => false,
            ]
        );

        if (Schema::hasTable('roles')) {
            $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'api']);

            if (! $manager->hasRole('manager')) {
                $manager->assignRole($managerRole);
            }
        }
    }

    /**
     * SEED_ADMIN_PASSWORD (config `seeding.admin_password`), or the documented
     * fallback in the local environment only. The value is never echoed.
     */
    private function password(): string
    {
        $configured = (string) config('seeding.admin_password', '');

        if ($configured !== '') {
            return $configured;
        }

        if (app()->environment('local')) {
            $this->command?->warn('SEED_ADMIN_PASSWORD is not set; using the local fallback password documented in .env.example.');

            return self::LOCAL_FALLBACK_PASSWORD;
        }

        throw new RuntimeException(
            'SEED_ADMIN_PASSWORD must be set to seed admin accounts outside the local environment. No accounts were changed.'
        );
    }
}
