<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medicines and prescriptions. The list of medicines is the clinic's own copy of
 * database/data/medicines.csv plus what the clinic adds; a prescription is a draft until the
 * doctor signs it, then it is locked and can only be cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();                      // from the list, or "custom-..." for the clinic's own
            $table->string('name', 150)->index();                      // the generic name
            $table->string('strength', 80)->nullable();
            $table->string('form', 30);                                 // tablet, syrup, injection ...
            $table->string('route', 20)->nullable();
            $table->string('class', 60)->nullable()->index();           // matched against allergies: penicillin, nsaid ...
            $table->decimal('mg_per_unit', 12, 4)->nullable();          // mg in one tablet, capsule or ml
            $table->boolean('controlled')->default(false);
            $table->text('brands')->nullable();                         // "PK: Panadol | IN: Crocin | ..."
            $table->decimal('child_max_mg_per_kg_dose', 10, 3)->nullable();   // written by the clinic's pharmacist
            $table->decimal('max_daily_mg', 12, 3)->nullable();
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique();         // RX-000001, given when signed
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('weight_kg', 5, 1)->nullable();            // the weight the doses were checked against
            $table->string('notes', 1000)->nullable();
            $table->string('status', 10)->default('draft');             // draft, signed, cancelled
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signature', 64)->nullable();
            $table->string('verify_token', 40)->nullable()->unique();   // the address in the QR code
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);                                // as printed, kept even if the list changes
            $table->string('strength', 80)->nullable();
            $table->string('form', 30)->nullable();
            $table->string('route', 20)->nullable();
            $table->decimal('dose_amount', 10, 3)->nullable();
            $table->string('dose_unit', 15)->nullable();                // tablet, ml, mg, drop, puff ...
            $table->string('frequency', 10)->nullable();                // od, bd, tds ... (App\Services\Prescribing::FREQUENCIES)
            $table->string('timing', 15)->nullable();                   // before_food, after_food ...
            $table->unsignedSmallInteger('days')->nullable();
            $table->string('quantity', 40)->nullable();
            $table->string('instructions', 255)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // what the checks found when the prescription was signed, and why the doctor went on
        Schema::create('prescription_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('message', 500);
            $table->string('reason', 255);
            $table->timestamp('created_at')->useCurrent();
        });

        // the prescriptions a doctor writes often ("Adult sore throat")
        Schema::create('prescription_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('items');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['prescription_sets', 'prescription_warnings', 'prescription_items', 'prescriptions', 'medicines'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
