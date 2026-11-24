<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Text messages to patients (reminders of appointments), and the patients who do not want them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 30);
            $table->text('body');
            $table->string('kind', 12);                         // reminder, booking, test
            $table->string('status', 10)->index();              // sent, failed, logged (no gateway: written here only)
            $table->string('error', 255)->nullable();
            $table->string('provider_id', 100)->nullable();     // the number the gateway gave the message
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable();
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('no_sms')->default(false);         // the patient asked for no text messages
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn('reminded_at'));
        Schema::table('patients', fn (Blueprint $table) => $table->dropColumn('no_sms'));
    }
};
