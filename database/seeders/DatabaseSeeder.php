<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Nothing is seeded into the central database: system administrators are made with
 * "php artisan app:create-admin" and a clinic gets its first records when it is opened.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
