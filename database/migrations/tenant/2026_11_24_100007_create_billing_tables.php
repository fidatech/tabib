<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing: the price list of the clinic, invoices and the money taken for them. Nothing is
 * deleted: money given back is a refund (a payment below zero), and a wrong invoice is voided
 * with a reason once nothing is left paid on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('name_local', 150)->nullable();
            $table->string('category', 20)->default('procedure');   // consultation, procedure, lab, imaging, pharmacy, other
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();                   // INV-000001
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('issued_on')->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('discount_reason', 255)->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);               // payments less refunds, kept with every payment
            $table->string('status', 10)->default('unpaid')->index(); // unpaid, partly, paid, void
            $table->string('note', 500)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 200);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();                   // RC-000001 for money taken, RF-000001 for money given back
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);                         // below zero: a refund
            $table->string('method', 10)->default('cash');            // cash, card, bank, mobile, other
            $table->string('reference', 80)->nullable();              // card slip, transfer or wallet number
            $table->string('note', 255)->nullable();                  // the reason of a refund
            $table->timestamp('received_at')->index();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('services');
    }
};
