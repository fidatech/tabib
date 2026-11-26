<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the clinic spends (rent, salaries, supplies ...), the share of each doctor (a percent of
 * the money taken for his patients, or an amount for every patient he saw) and what was paid to him.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('commission_type', 10)->default('none');   // none, percent, per_visit
            $table->decimal('commission_value', 12, 2)->default(0);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();                   // EX-000001
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('spent_on')->index();
            $table->string('category', 20)->index();
            $table->decimal('amount', 12, 2);
            $table->string('method', 10)->default('cash');
            $table->string('paid_to', 150)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();      // a commission paid to a doctor
            $table->string('commission_month', 7)->nullable();                              // 2026-11
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::table('doctors', fn (Blueprint $table) => $table->dropColumn(['commission_type', 'commission_value']));
    }
};
