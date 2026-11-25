<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Note templates per specialty (general, dental with a tooth chart, antenatal), the structured
 * part of a visit they bring, and the link of a merged patient to the record he was merged into.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('kind', 12)->default('general');     // general, dental (tooth chart), antenatal (pregnancy form)
            $table->text('complaint')->nullable();
            $table->text('history')->nullable();
            $table->text('examination')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('encounters', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->constrained('note_templates')->nullOnDelete();
            $table->string('kind', 12)->nullable();             // the structured part: dental, antenatal
            $table->json('extra')->nullable();                  // the tooth chart or the pregnancy form; signed with the note
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('merged_into_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->timestamp('merged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
            $table->dropColumn('merged_at');
        });
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn(['kind', 'extra']);
        });
        Schema::dropIfExists('note_templates');
    }
};
