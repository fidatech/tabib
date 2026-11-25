<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The papers a doctor gives: lab and imaging requests (with their results later), referral
 * letters and medical certificates; and who reviewed the history of the patient in a visit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->timestamp('history_reviewed_at')->nullable();
            $table->foreignId('history_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();               // LAB-000001, IMG-000001
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);                           // lab, imaging
            $table->json('tests');                                // the names of the tests asked for
            $table->string('urgency', 10)->default('routine');    // routine, urgent
            $table->string('clinical_note', 500)->nullable();     // what the lab or radiologist should know
            $table->string('status', 12)->default('ordered');     // ordered, resulted, cancelled
            $table->text('result')->nullable();
            $table->foreignId('result_document_id')->nullable()->constrained('patient_documents')->nullOnDelete();
            $table->timestamp('resulted_at')->nullable();
            $table->foreignId('resulted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'status']);
        });

        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();               // REF-000001, MC-000001
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->string('kind', 12);                           // referral, sick_leave, fitness, general
            $table->string('lang', 2)->default('en');
            $table->string('addressed_to', 200)->nullable();      // the doctor, hospital or employer
            $table->date('rest_from')->nullable();
            $table->date('rest_to')->nullable();
            $table->text('body');
            $table->string('status', 10)->default('issued');      // issued, cancelled: a letter given is never changed
            $table->string('cancel_reason', 255)->nullable();
            $table->string('signature', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
        Schema::dropIfExists('orders');
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('history_reviewed_by');
            $table->dropColumn('history_reviewed_at');
        });
    }
};
