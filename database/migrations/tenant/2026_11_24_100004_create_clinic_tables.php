<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The first clinic tables: the doctors, the kinds of appointment, the patients and their
 * appointments. Patients are never deleted for good: a record keeps its history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();   // the doctor signs in as this user
            $table->string('specialty', 120)->nullable();
            $table->string('licence_no', 60)->nullable();          // the licence of the doctor, printed on prescriptions
            $table->decimal('fee', 12, 2)->default(0);             // the usual consultation fee
            $table->string('colour', 20)->default('teal');         // of the doctor's appointments on the calendar
            $table->string('room', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('appointment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('name_local', 120)->nullable();
            $table->unsignedSmallInteger('minutes')->default(15);
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();                // medical record number: P-000001
            $table->string('name', 150)->index();
            $table->string('father_name', 150)->nullable();
            $table->string('gender', 10);                          // male, female, other
            $table->date('date_of_birth')->nullable();
            $table->boolean('dob_estimated')->default(false);      // only the age was known
            $table->string('phone', 40)->nullable()->index();
            $table->string('phone2', 40)->nullable();
            $table->string('tazkira', 40)->nullable()->index();    // national identity card
            $table->string('blood_group', 5)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('emergency_name', 150)->nullable();
            $table->string('emergency_phone', 40)->nullable();
            $table->string('allergies', 500)->nullable();          // known allergies, shown in red on every page of the patient
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // booked, confirmed, arrived, with_doctor, completed, no_show, cancelled
            $table->string('status', 15)->default('booked')->index();
            $table->string('source', 10)->default('desk');         // desk, walk_in, online
            $table->unsignedSmallInteger('token')->nullable();     // the number of the patient in the queue of the day
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('reason', 255)->nullable();             // why the patient comes
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['doctor_id', 'starts_at']);
            $table->index(['branch_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('appointment_types');
        Schema::dropIfExists('doctors');
    }
};
