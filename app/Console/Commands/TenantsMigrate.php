<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantManager;
use App\Tenancy\TenantSetup;
use Illuminate\Console\Command;
use Throwable;

class TenantsMigrate extends Command
{
    protected $signature = 'tenants:migrate {slug? : only this clinic}';

    protected $description = 'Bring the tables of every clinic up to date (run after each update of the system)';

    public function handle(Tenancy $tenancy, TenantManager $manager, TenantSetup $setup): int
    {
        $tenants = Tenant::query()
            ->when($this->argument('slug'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('id')
            ->get();

        $failed = 0;
        foreach ($tenants as $tenant) {
            try {
                $tenancy->run($tenant, function () use ($manager, $setup) {
                    $manager->migrate();
                    $setup->baseData();
                });
                $this->info("{$tenant->slug}: up to date");
            } catch (Throwable $e) {
                $failed++;
                $this->error("{$tenant->slug}: {$e->getMessage()}");
            }
        }

        $this->line($tenants->count().' clinic(s), '.$failed.' failed.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
