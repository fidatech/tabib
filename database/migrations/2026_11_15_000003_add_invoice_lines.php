<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The lines of a licence invoice (first year or renewal). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('lines')->nullable()->after('branches');          // [{label, quantity, price, amount}]
            $table->unsignedInteger('new_branches')->default(0)->after('branches'); // of "branches", how many are billed their first year
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['lines', 'new_branches']);
        });
    }
};
