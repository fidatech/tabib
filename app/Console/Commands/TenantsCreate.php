<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TenantsCreate extends Command
{
    protected $signature = 'tenants:create {slug : short name used in the address and the database}
        {--name= : name of the clinic}
        {--domain= : its own domain; empty means <slug>.<base domain>}
        {--owner-name= : full name of the owner}
        {--owner-email= : email the owner signs in with}
        {--owner-password= : password of the owner}';

    protected $description = 'Open a new clinic: its database, tables, main branch and owner';

    public function handle(TenantManager $manager): int
    {
        $password = $this->option('owner-password');
        $data = [
            'slug' => strtolower((string) $this->argument('slug')),
            'name' => (string) ($this->option('name') ?? $this->ask('Name of the clinic')),
            'domain' => $this->option('domain'),
            'owner_name' => (string) ($this->option('owner-name') ?? $this->ask('Full name of the owner')),
            'owner_email' => strtolower((string) ($this->option('owner-email') ?? $this->ask('Email of the owner'))),
            'owner_password' => (string) ($password ?? $this->secret('Password of the owner (at least 8 characters)')),
        ];

        $validator = Validator::make($data, [
            'slug' => ['required', 'regex:/^[a-z][a-z0-9-]{2,39}$/', Rule::notIn(config('tenancy.reserved_slugs')), Rule::unique(Tenant::class, 'slug')],
            'name' => ['required', 'string', 'max:150'],
            'domain' => ['nullable', 'string', 'max:190', Rule::unique(Tenant::class, 'domain')],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_password' => ['required', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $tenant = $manager->create($data);

        $this->info("{$tenant->name} is ready at {$tenant->domain} (database {$tenant->database}).");

        return self::SUCCESS;
    }
}
