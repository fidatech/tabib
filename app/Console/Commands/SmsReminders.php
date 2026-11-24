<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Sms;
use App\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Throwable;

class SmsReminders extends Command
{
    protected $signature = 'sms:reminders {slug? : only this clinic}';

    protected $description = 'Send the text reminders of tomorrow\'s appointments, in every clinic that sends them, after the hour it chose';

    public function handle(Tenancy $tenancy): int
    {
        $tenants = Tenant::where('status', Tenant::ACTIVE)->when($this->argument('slug'), fn ($q, $slug) => $q->where('slug', $slug))->orderBy('id')->get();
        $total = 0;
        foreach ($tenants as $tenant) {
            try {
                $sent = $tenancy->run($tenant, fn () => Sms::remind());
                $total += $sent;
                if ($sent) {
                    $this->info($tenant->slug.': '.$sent);
                }
            } catch (Throwable $e) {
                report($e);
                $this->error($tenant->slug.': '.$e->getMessage());
            }
        }
        $this->line($total.' reminder(s).');

        return self::SUCCESS;
    }
}
