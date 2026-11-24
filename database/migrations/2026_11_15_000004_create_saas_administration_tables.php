<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of the administration of the service: roles for the staff of Fida Technologies,
 * payments, announcements, support tickets and backups; and why a clinic was locked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('role', 20)->default('owner')->after('email');   // owner, sales, accountant, support
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('locked_reason', 20)->nullable()->after('status'); // "licence": locked by the system when the licence ran out
            $table->string('owner_name', 150)->nullable()->after('licence_no');
            $table->string('owner_phone', 40)->nullable()->after('owner_name');
            $table->string('owner_email', 190)->nullable()->after('owner_phone');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30)->unique();
            $table->decimal('amount', 14, 2);                               // US dollars
            $table->string('method', 20);                                   // cash, bank, hesabpay, other
            $table->string('reference', 120)->nullable();                   // slip or transaction number
            $table->date('paid_on');
            $table->string('note', 500)->nullable();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'paid_on']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // empty: every clinic
            $table->string('title', 150);
            $table->text('body');
            $table->string('level', 10)->default('info');                   // info, warning, danger
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20)->unique();
            $table->string('subject', 200);
            $table->string('status', 12)->default('open')->index();         // open (waiting for Fida), answered (waiting for the clinic), closed
            $table->string('priority', 10)->default('normal');              // normal, urgent
            $table->string('opened_by', 150);
            $table->string('opened_email', 190)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('side', 10);                                     // client, support
            $table->string('author', 150);
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('file', 255);                                    // under storage/app/backups
            $table->unsignedBigInteger('size')->default(0);
            $table->string('kind', 10)->default('auto');                    // auto, manual
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['backups', 'ticket_messages', 'tickets', 'announcements', 'payments'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['locked_reason', 'owner_name', 'owner_phone', 'owner_email']));
        Schema::table('admins', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
