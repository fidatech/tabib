<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What the plan of a clinic allows besides branches: doctors, and text messages a month. Empty: no limit. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('max_doctors')->nullable()->after('max_branches');
            $table->unsignedInteger('max_sms_month')->nullable()->after('max_doctors');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['max_doctors', 'max_sms_month']));
    }
};
