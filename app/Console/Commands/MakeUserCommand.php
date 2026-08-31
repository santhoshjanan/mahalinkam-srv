<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeUserCommand extends Command
{
    protected $signature = 'mahalinkam:make-user {email} {name} {--password=}';

    protected $description = 'Create a verified mahalinkam user account';

    public function handle(): int
    {
        $data = [
            'email' => $this->argument('email'),
            'name' => $this->argument('name'),
            'password' => $this->option('password') ?: $this->secret('Password (min 8 chars)'),
        ];

        $v = Validator::make($data, [
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'email' => $data['email'],
            'name' => $data['name'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        $this->info("Created user #{$user->id} <{$user->email}>");

        return self::SUCCESS;
    }
}
