<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resellers: the salesmen and marketing agents who bring the clinics. A reseller has a commission
 * of his own (a percentage of what his client pays, or a flat amount for each sale); he may take
 * the money from the client himself and hand it over later, so he has an account with us.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('commission_type', 10)->default('percent');   // percent of what the client pays, or flat for each sale (invoice)
            $table->decimal('commission_value', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('status')->constrained('sellers')->nullOnDelete();   // who sold it
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('admin_id')->constrained('sellers')->nullOnDelete();   // who earns the commission
            $table->boolean('collected_by_seller')->default(false)->after('seller_id');                              // the reseller took the money and still has to hand it over
            $table->decimal('commission', 14, 2)->default(0)->after('collected_by_seller');
        });

        // money between a reseller and us: what he hands over ("in"), and commission we pay him ("out")
        Schema::create('seller_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->string('direction', 3);
            $table->decimal('amount', 14, 2);
            $table->string('method', 20);
            $table->string('reference', 120)->nullable();
            $table->date('paid_on');
            $table->string('note', 500)->nullable();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_settlements');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_id');
            $table->dropColumn(['collected_by_seller', 'commission']);
        });
        Schema::table('tenants', fn (Blueprint $table) => $table->dropConstrainedForeignId('seller_id'));
        Schema::dropIfExists('sellers');
    }
};
