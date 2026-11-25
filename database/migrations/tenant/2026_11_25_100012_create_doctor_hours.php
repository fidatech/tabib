<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When each doctor works (by day of the week, one or two periods), his days off, and the appointments booked as a series. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');              // 0 Sunday ... 6 Saturday
            $table->string('starts', 5);                         // 08:00
            $table->string('ends', 5);                           // 12:30
            $table->timestamps();
            $table->index(['doctor_id', 'weekday']);
        });

        Schema::create('doctor_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason', 150)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['doctor_id', 'starts_on', 'ends_on']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('series_id')->nullable()->index();   // the first appointment of a course (physiotherapy ...)
            $table->boolean('outside_hours')->default(false);              // booked on purpose outside the hours of the doctor
        });
    }

    public function down(): void
    {
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn(['series_id', 'outside_hours']));
        Schema::dropIfExists('doctor_leaves');
        Schema::dropIfExists('doctor_hours');
    }
};
