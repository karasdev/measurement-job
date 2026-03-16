<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class FilamentAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = (string) env('FILAMENT_ADMIN_NAME', '');
        $email = (string) env('FILAMENT_ADMIN_EMAIL', '');
        $password = (string) env('FILAMENT_ADMIN_PASSWORD', '');

        if ($name === '' || $email === '' || $password === '') {
            $this->command?->warn('Skipping Filament admin seed: set FILAMENT_ADMIN_NAME, FILAMENT_ADMIN_EMAIL, and FILAMENT_ADMIN_PASSWORD in .env.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info("Filament admin user seeded: {$email}");
    }
}
