<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When the patient was reminded of the check-up the doctor asked for ("come back on"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->timestamp('follow_up_reminded_at')->nullable();
            $table->index('follow_up_on');
        });
    }

    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropIndex(['follow_up_on']);
            $table->dropColumn('follow_up_reminded_at');
        });
    }
};
