<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The list of medicines of the whole platform, kept by Fida Technologies in the central panel
 * (php artisan medicines:import loads database/data/medicines.csv into it). Every clinic gets a
 * copy it cannot change, except its own dose limits and the medicines it does not use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_list', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('name', 150)->index();
            $table->string('strength', 80)->nullable();
            $table->string('form', 30);
            $table->string('route', 20)->nullable();
            $table->string('class', 60)->nullable();
            $table->decimal('mg_per_unit', 10, 3)->nullable();
            $table->boolean('controlled')->default(false);
            $table->text('brands')->nullable();
            $table->boolean('is_active')->default(true);          // off: withdrawn from every clinic
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_list');
    }
};
