<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateDefaultUsers extends Command
{
    protected $signature = 'users:create-default';

    protected $description = 'Create Super Admin and Owner accounts';

    public function handle(): int
    {
        $accounts = [
            // [
            //     'name' => 'Super Admin',
            //     'email' => 'admin@codeagiles.com',
            //     'role' => 'super_admin',
            // ],
            [
                'name' => 'Store Owner',
                'email' => 'owner@codeagiles.com',
                'role' => 'owner',
            ],
        ];

        foreach ($accounts as $account) {
            $this->info("Setting up {$account['role']} account");

            $password = $this->secret(
                "Enter password for {$account['email']}"
            );

            if (!$password || strlen($password) < 12) {
                $this->error('Password must be at least 12 characters.');
                return self::FAILURE;
            }

            $confirmation = $this->secret(
                'Confirm password'
            );

            if ($password !== $confirmation) {
                $this->error('Passwords do not match.');
                return self::FAILURE;
            }

            $user = User::where('email', $account['email'])->first();

            if ($user) {
                $this->warn(
                    "Account {$account['email']} already exists. Skipping."
                );
                continue;
            }

            $user = new User();

            $user->name = $account['name'];
            $user->email = $account['email'];
            $user->role = $account['role'];
            $user->password = Hash::make($password);

            $user->save();

            $this->info(
                "{$account['role']} created successfully!"
            );
        }

        $this->newLine();
        $this->info('Account setup finished.');

        return self::SUCCESS;
    }
}