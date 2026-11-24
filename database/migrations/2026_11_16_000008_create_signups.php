<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A clinic asks for the system on the website. The request waits for the administrator, who
 * approves it (the clinic is then opened) or rejects it with a reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signups', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();              // the applicant follows the request with it
            $table->string('name', 150);
            $table->string('name_local', 150)->nullable();
            $table->string('slug', 40);                         // the address asked for: <slug>.<base domain>
            $table->string('licence_no', 60)->nullable();          // the licence of the Ministry of Public Health
            $table->string('clinic_type', 30)->nullable();          // general, dental, gynecology, pediatrics, other
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->unsignedSmallInteger('doctors')->default(1);    // doctors who will use the system
            $table->unsignedSmallInteger('branches')->default(0);   // branches it has besides the main one
            $table->string('owner_name', 150);
            $table->string('owner_email', 190);
            $table->string('owner_phone', 40);
            $table->string('password')->nullable();             // hashed; removed once the request is decided
            $table->string('message', 1000)->nullable();
            $table->string('status', 12)->default('pending');   // pending, approved, rejected
            $table->string('reason', 500)->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signups');
    }
};
