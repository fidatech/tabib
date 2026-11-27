<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booking from the public page of the clinic: the time is asked for, a code goes by SMS to the
 * phone, and only with the code is the appointment made. Each doctor can be closed to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();                // the address of the booking for the patient (to see or cancel it)
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->string('name', 150);
            $table->string('phone', 30);
            $table->string('gender', 10);
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('code_hash', 255);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('code_expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['phone', 'confirmed_at']);
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->boolean('books_online')->default(true);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('registered_online')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('patients', fn (Blueprint $table) => $table->dropColumn('registered_online'));
        Schema::table('doctors', fn (Blueprint $table) => $table->dropColumn('books_online'));
        Schema::dropIfExists('online_bookings');
    }
};
