<?php

namespace App\Console\Commands;

use App\Models\Central\CentralSetting;
use App\Models\Central\Tenant;
use App\Services\Backups;
use Illuminate\Console\Command;
use Throwable;

class TenantsBackup extends Command
{
    protected $signature = 'tenants:backup {slug? : only this clinic}';

    protected $description = 'Save a copy of the database of every clinic, and remove the copies that are too old';

    public function handle(): int
    {
        $failed = 0;
        $tenants = Tenant::where('status', '!=', Tenant::ARCHIVED)->when($this->argument('slug'), fn ($q, $slug) => $q->where('slug', $slug))->orderBy('id')->get();
        foreach ($tenants as $tenant) {
            try {
                $backup = Backups::make($tenant);
                $this->info($tenant->slug.': '.$backup->file);
            } catch (Throwable $e) {
                $failed++;
                report($e);
                $this->error($tenant->slug.': '.$e->getMessage());
            }
        }
        $removed = Backups::prune((int) (CentralSetting::read('backup_keep_days') ?: 14));
        $this->line($tenants->count().' clinic(s), '.$failed.' failed, '.$removed.' old copies removed.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
