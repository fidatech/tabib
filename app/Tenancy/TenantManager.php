<?php

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/** Opens a new clinic: its record, its database, its tables and its first records. */
class TenantManager
{
    public function __construct(private Tenancy $tenancy, private TenantDatabase $databases, private TenantSetup $setup, private Cpanel $cpanel) {}

    /**
     * @param  array{name: string, slug: string, owner_name: string, owner_email: string, owner_password: string}  $data
     *                                                                                                                  plus the optional keys of the tenants table (domain, name_local, licence_no ...)
     */
    public function create(array $data): Tenant
    {
        $slug = strtolower($data['slug']);
        $database = config('tenancy.database_prefix').str_replace('-', '_', $slug);

        $tenant = Tenant::create([
            'name' => $data['name'],
            'name_local' => $data['name_local'] ?? null,
            'slug' => $slug,
            'domain' => strtolower($data['domain'] ?? '') ?: $slug.'.'.config('tenancy.base_domain'),
            'database' => $database,
            'status' => Tenant::ACTIVE,
            'licence_no' => $data['licence_no'] ?? null,
            'main_branch_price' => $data['main_branch_price'] ?? 0,
            'branch_price' => $data['branch_price'] ?? 0,
            'max_branches' => $data['max_branches'] ?? null,
            'max_doctors' => $data['max_doctors'] ?? null,
            'max_sms_month' => $data['max_sms_month'] ?? null,
            'licence_expires_on' => $data['licence_expires_on'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $created = false;
        try {
            $this->databases->create($database);
            $created = true;
            $this->tenancy->run($tenant, function () use ($tenant, $data) {
                $this->migrate();
                $this->setup->run($tenant, $data);
            });
        } catch (Throwable $e) {
            // nothing half-made is left behind
            if ($created) {
                try {
                    $this->databases->drop($database);
                } catch (Throwable $ignored) {
                    report($ignored);
                }
            }
            $tenant->delete();

            throw $e;
        }

        // cPanel hosting: the address of the clinic is made too; if the panel refuses, the clinic stays and the log says why
        if ($this->cpanel->enabled()) {
            try {
                $this->cpanel->addAddress($tenant->domain);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $tenant;
    }

    /** Bring the tables of the clinic we are in up to date. */
    public function migrate(): void
    {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }
}
