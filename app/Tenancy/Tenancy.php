<?php

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Which clinic the current request (or command) works in.
 *
 * Every clinic has a database of its own. While one is chosen, the default database
 * connection is "tenant" and points at that database, so ordinary models, validation
 * rules and roles read and write there. Models of the central database (the list of
 * clinics, system administrators) always name the central connection themselves.
 */
class Tenancy
{
    public ?Tenant $tenant = null;

    /** Name of the connection that holds the central database. */
    public function central(): string
    {
        return (string) (config('tenancy.central_connection') ?: config('database.default'));
    }

    public function initialize(Tenant $tenant): void
    {
        // remember the central connection before the default changes
        config(['tenancy.central_connection' => $this->central()]);

        config(['database.connections.tenant' => $this->connectionConfig($tenant->database)]);
        DB::purge('tenant');
        config(['database.default' => 'tenant']);
        DB::setDefaultConnection('tenant');

        $this->tenant = $tenant;
        $this->forgetWhatWasRemembered('tenant.'.$tenant->getKey());
    }

    /** Back to the central database. */
    public function end(): void
    {
        $central = $this->central();
        config(['database.default' => $central]);
        DB::setDefaultConnection($central);
        DB::purge('tenant');

        $this->tenant = null;
        $this->forgetWhatWasRemembered('central');
    }

    /** Do some work inside a clinic, then return to where we were. */
    public function run(Tenant $tenant, callable $work): mixed
    {
        $before = $this->tenant;
        $this->initialize($tenant);

        try {
            return $work();
        } finally {
            $before ? $this->initialize($before) : $this->end();
        }
    }

    /** Connection settings of a clinic's database: the central ones with another database name. */
    public function connectionConfig(string $database): array
    {
        $config = config('database.connections.'.$this->central());
        unset($config['url']);
        $config['database'] = $config['driver'] === 'sqlite' ? $this->sqliteFile($database) : $database;

        return $config;
    }

    public function sqliteFile(string $database): string
    {
        return rtrim((string) config('tenancy.sqlite_path'), '/').'/'.$database.'.sqlite';
    }

    /** Settings and permissions kept in memory belong to one clinic only. */
    private function forgetWhatWasRemembered(string $scope): void
    {
        app()->forgetInstance('erp.settings');

        config(['permission.cache.key' => 'spatie.permission.cache.'.$scope]);
        if (app()->resolved(PermissionRegistrar::class)) {
            $registrar = app(PermissionRegistrar::class);
            $registrar->initializeCache();
            $registrar->clearPermissionsCollection();
        }
    }
}
