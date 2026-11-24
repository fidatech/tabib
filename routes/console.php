<?php

use Illuminate\Support\Facades\Schedule;

// Commands live in app/Console/Commands:
//   app:create-admin   a system administrator of the central panel
//   tenants:create     a new clinic
//   tenants:migrate    update the tables of every clinic
//   licences:check     lock the clinics whose licence has ended, open those that were paid
//   tenants:backup     save a copy of the database of every clinic

// The server's cron runs "php artisan schedule:run" every minute.
Schedule::command('licences:check')->dailyAt('00:20')->timezone('Asia/Kabul');
Schedule::command('tenants:backup')->dailyAt('01:30')->timezone('Asia/Kabul');

// cPanel hosting without a wildcard certificate: a clinic opened in the last two days may still wait for
// the certificate of its address, so the panel is asked again every hour until its own round has passed
Schedule::call(function () {
    $cpanel = app(\App\Tenancy\Cpanel::class);
    if ($cpanel->enabled() && ! config('tenancy.cpanel.wildcard') && \App\Models\Central\Tenant::where('created_at', '>=', now()->subDays(2))->exists()) {
        $cpanel->askForCertificates();
    }
})->hourly()->name('certificates of new clinics');
