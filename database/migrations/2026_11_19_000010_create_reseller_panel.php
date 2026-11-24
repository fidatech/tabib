<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The panel of the resellers. A reseller signs in with his email and a password; he asks for new
 * clinics to be opened (a registration request that carries his name), and he reports the money
 * he took from a client, which counts once the administrator confirms it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');       // empty: he cannot sign in yet
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::table('signups', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('tenant_id')->constrained('sellers')->nullOnDelete();   // asked for by this reseller
        });

        // money a reseller says he took from a client: a payment is made of it when the administrator confirms
        Schema::create('seller_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 20);
            $table->string('reference', 120)->nullable();
            $table->date('paid_on');
            $table->string('note', 500)->nullable();
            $table->string('status', 12)->default('pending')->index();   // pending, confirmed, rejected
            $table->string('reason', 500)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_claims');
        Schema::table('signups', fn (Blueprint $table) => $table->dropConstrainedForeignId('seller_id'));
        Schema::table('sellers', fn (Blueprint $table) => $table->dropColumn(['password', 'remember_token', 'last_login_at']));
    }
};
