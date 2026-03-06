<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    protected $signature = 'user:admin {email : The user email}';

    protected $description = 'Set a user as admin by email';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User not found: {$email}");

            return 1;
        }

        $user->update(['is_admin' => true]);
        $this->info("User {$email} is now an admin.");

        return 0;
    }
}
