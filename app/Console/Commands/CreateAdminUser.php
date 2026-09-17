<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'jannayaks:create-admin
                            {--email= : Admin email}
                            {--name=Admin : Display name}
                            {--password= : Password (min 8 characters)}';

    protected $description = 'Create or promote a Filament staff admin user (password login).';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: $this->ask('Admin email'));
        $name = (string) ($this->option('name') ?: 'Admin');
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ], [
            'email' => ['required', 'email:strict', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

        if ($user instanceof User) {
            $user->fill([
                'name' => $name,
                'password' => Hash::make($password),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'account_status' => 'active',
            ]);
            $user->save();
            $this->info("Updated existing user #{$user->id} to admin.");
        } else {
            $user = User::query()->create([
                'name' => $name,
                'email' => strtolower($email),
                'password' => Hash::make($password),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
                'account_status' => 'active',
            ]);
            $this->info("Created admin user #{$user->id}.");
        }

        return self::SUCCESS;
    }
}
