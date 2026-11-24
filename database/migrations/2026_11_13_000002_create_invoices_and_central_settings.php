<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Licence invoices of the clinics, and the few settings of the system administrator panel (the DAB view-only account). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('main_branch_price', 14, 2);
            $table->decimal('branch_price', 14, 2);
            $table->unsignedInteger('branches');                        // dependent branches on the day of the invoice
            $table->decimal('total', 14, 2);
            $table->string('status', 12)->default('unpaid')->index();   // unpaid, paid, cancelled
            $table->date('paid_on')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('central_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_settings');
        Schema::dropIfExists('invoices');
    }
};
