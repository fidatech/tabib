<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A medicine Fida Technologies took off the platform list: it can no longer be prescribed in any clinic. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->boolean('withdrawn')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('medicines', fn (Blueprint $table) => $table->dropColumn('withdrawn'));
    }
};
