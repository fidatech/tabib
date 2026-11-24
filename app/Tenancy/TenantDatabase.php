<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Creates and removes the database of a clinic. */
class TenantDatabase
{
    public function __construct(private Tenancy $tenancy, private Cpanel $cpanel) {}

    public function create(string $name): void
    {
        $this->check($name);

        if ($this->driver() === 'sqlite') {
            $file = $this->tenancy->sqliteFile($name);
            if (! is_dir(dirname($file))) {
                mkdir(dirname($file), 0775, true);
            }
            if (is_file($file)) {
                throw new InvalidArgumentException("The database $name already exists.");
            }
            touch($file);

            return;
        }

        // cPanel hosting: the panel makes the database and gives the MySQL user of the system its rights on it
        if ($this->cpanel->enabled()) {
            $this->cpanel->createDatabase($name, (string) config('database.connections.'.$this->tenancy->central().'.username'));

            return;
        }

        DB::connection($this->tenancy->central())->statement("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function drop(string $name): void
    {
        $this->check($name);
        DB::purge('tenant');

        if ($this->driver() === 'sqlite') {
            $file = $this->tenancy->sqliteFile($name);
            if (is_file($file)) {
                unlink($file);
            }

            return;
        }

        if ($this->cpanel->enabled()) {
            $this->cpanel->dropDatabase($name);

            return;
        }

        DB::connection($this->tenancy->central())->statement("DROP DATABASE IF EXISTS `$name`");
    }

    private function driver(): string
    {
        return (string) config('database.connections.'.$this->tenancy->central().'.driver');
    }

    /** The name is written into the statement, so it may hold nothing but small letters, digits and "_". */
    private function check(string $name): void
    {
        if (! preg_match('/^[a-z0-9_]{1,60}$/', $name)) {
            throw new InvalidArgumentException('A database name may only have small letters, digits and "_".');
        }
    }
}
