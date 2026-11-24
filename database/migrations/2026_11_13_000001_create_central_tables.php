<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The central database: the list of clinics and the system administrators of Fida Technologies. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('name_local', 150)->nullable();
            $table->string('slug', 40)->unique();
            $table->string('domain', 190)->unique();
            $table->string('database', 60)->unique();
            $table->string('status', 20)->default('active')->index(); // active, suspended, archived
            $table->string('licence_no', 60)->nullable();              // the Ministry of Public Health licence of the clinic
            // software licence: one price for the main branch, another for every dependent branch
            $table->decimal('main_branch_price', 14, 2)->default(0);
            $table->decimal('branch_price', 14, 2)->default(0);
            $table->unsignedInteger('max_branches')->nullable();        // dependent branches allowed; empty = no limit
            $table->date('licence_expires_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('description', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('tenants');
    }
};
