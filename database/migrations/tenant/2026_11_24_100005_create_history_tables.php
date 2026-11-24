<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The history of a patient, the visits with the doctor, and the record of every change.
 * Nothing here is deleted: an entry is resolved, stopped, or marked "entered in error" with a
 * reason, and every change keeps the old value in history_revisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        // the columns every entry of the history has
        $common = function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->index();          // the visit it was written in
            $table->string('status', 20)->default('active')->index();      // see App\Support\History
            $table->string('source', 12)->default('clinician');            // clinician, patient, imported
            $table->string('error_reason', 255)->nullable();               // why it was marked entered in error
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        };

        Schema::create('patient_problems', function (Blueprint $table) use ($common) {
            $common($table);
            $table->string('description', 255);
            $table->string('icd10', 10)->nullable()->index();
            $table->date('onset_on')->nullable();
            $table->date('resolved_on')->nullable();
            $table->string('note', 500)->nullable();
        });

        Schema::create('patient_allergies', function (Blueprint $table) use ($common) {
            $common($table);
            $table->string('substance', 150);                               // a medicine, a food, ...
            $table->string('reaction', 255)->nullable();
            $table->string('severity', 10)->nullable();                     // mild, moderate, severe
            $table->string('note', 500)->nullable();
        });

        Schema::create('patient_medications', function (Blueprint $table) use ($common) {
            $common($table);
            $table->string('name', 150);
            $table->string('dose', 60)->nullable();
            $table->string('frequency', 60)->nullable();
            $table->string('route', 30)->nullable();
            $table->date('started_on')->nullable();
            $table->date('stopped_on')->nullable();
            $table->string('stop_reason', 255)->nullable();
            $table->string('note', 500)->nullable();
        });

        Schema::create('patient_surgeries', function (Blueprint $table) use ($common) {
            $common($table);
            $table->string('procedure', 200);
            $table->string('performed', 20)->nullable();                    // a date or only a year
            $table->string('place', 150)->nullable();
            $table->string('note', 500)->nullable();
        });

        Schema::create('patient_family_history', function (Blueprint $table) use ($common) {
            $common($table);
            $table->string('relative', 30);
            $table->string('condition', 200);
            $table->string('note', 500)->nullable();
        });

        Schema::create('patient_immunizations', function (Blueprint $table) use ($common) {
            $common($table);
            $table->string('vaccine', 150);
            $table->string('dose_no', 20)->nullable();
            $table->date('given_on')->nullable();
            $table->string('batch', 60)->nullable();
            $table->string('note', 500)->nullable();
        });

        // one row per patient: smoking, naswar, work ...
        Schema::create('patient_social', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained()->restrictOnDelete();
            $table->string('smoking', 10)->nullable();                      // never, former, current
            $table->string('naswar', 10)->nullable();
            $table->string('alcohol', 10)->nullable();
            $table->string('occupation', 150)->nullable();
            $table->string('marital_status', 15)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vitals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->index();
            $table->dateTime('taken_at')->index();
            $table->unsignedSmallInteger('systolic')->nullable();
            $table->unsignedSmallInteger('diastolic')->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();               // °C
            $table->unsignedSmallInteger('respiration')->nullable();
            $table->unsignedTinyInteger('spo2')->nullable();
            $table->decimal('weight', 5, 1)->nullable();                    // kg
            $table->decimal('height', 5, 1)->nullable();                    // cm
            $table->unsignedSmallInteger('blood_sugar')->nullable();        // mg/dL
            $table->string('status', 20)->default('active');
            $table->string('error_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // a visit with the doctor: written as a draft, then signed and locked
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->dateTime('started_at');
            $table->text('complaint')->nullable();                          // chief complaint
            $table->text('history')->nullable();                            // history of the present illness
            $table->text('examination')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->date('follow_up_on')->nullable();
            $table->string('status', 10)->default('draft');                 // draft, signed
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signature', 64)->nullable();                    // sha-256 of what was signed: a later change shows
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'started_at']);
        });

        Schema::create('encounter_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->string('icd10', 10)->nullable();
            $table->string('description', 255);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        // what is added to a signed visit: the visit itself never changes
        Schema::create('encounter_addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->index();
            $table->string('kind', 20);                                     // lab, imaging, referral, consent, other
            $table->string('title', 150);
            $table->date('dated_on')->nullable();
            $table->string('path', 255);                                    // encrypted file (App\Services\Vault)
            $table->string('original_name', 200);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->string('status', 20)->default('active');
            $table->string('error_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // every change of the history: who, when, from what to what, and why
        Schema::create('history_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('action', 20);                                   // created, updated, entered_in_error ...
            $table->json('changes')->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['history_revisions', 'patient_documents', 'encounter_addenda', 'encounter_diagnoses', 'encounters', 'vitals', 'patient_social',
            'patient_immunizations', 'patient_family_history', 'patient_surgeries', 'patient_medications', 'patient_allergies', 'patient_problems'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
