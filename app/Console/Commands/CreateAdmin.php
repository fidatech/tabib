<?php

namespace App\Console\Commands;

use App\Models\Central\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {--name=} {--email=} {--password=}';

    protected $description = 'Create a system administrator of the central panel (asks for what is not given)';

    public function handle(): int
    {
        $password = $this->option('password');
        $data = [
            'name' => trim((string) ($this->option('name') ?? $this->ask('Full name'))),
            'email' => strtolower(trim((string) ($this->option('email') ?? $this->ask('Email address')))),
            'password' => (string) ($password ?? $this->secret('Password (at least 8 characters)')),
        ];
        $data['password_confirmation'] = (string) ($password ?? $this->secret('Repeat the password'));

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(Admin::class, 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        Admin::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);

        $this->info("System administrator {$data['email']} created. Sign in at /admin.");

        return self::SUCCESS;
    }
}
