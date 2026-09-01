<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {--email=} {--name=}';

    protected $description = 'Create or promote an administrator without default credentials';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Administrator email');
        $name = $this->option('name') ?: $this->ask('Administrator name');
        $password = $this->secret('Strong password (12+ characters)');
        $validation = Validator::make(compact('email', 'name', 'password'), ['email' => ['required', 'email'], 'name' => ['required', 'string', 'max:255'], 'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::updateOrCreate(['email' => strtolower($email)], ['name' => $name, 'password' => Hash::make($password), 'is_admin' => true]);
        $this->info('Administrator created or promoted. No password was logged.');

        return self::SUCCESS;
    }
}
