<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The diagnosis codes every clinic searches (ICD-10-CM), kept once in the central database: php artisan icd10:import */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icd10_codes', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('description', 255)->index();
            $table->string('description_local', 255)->nullable();   // a Dari or Pashto name, when one is written
            $table->boolean('billable')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icd10_codes');
    }
};
