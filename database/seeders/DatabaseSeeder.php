<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Order matters: permissions must exist before any role-grant logic
        // and before the admin user is created (the seeder below uses Spatie's
        // role cache, which reads from the permissions table).
        $this->call([
            AdminPermissionsSeeder::class,
            AdminUserSeeder::class,
        ]);

        User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => Hash::make('password'),
        ]);
    }
}
