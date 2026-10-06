<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Tutor: curriculum lessons generated once and used by every student.
 *
 * A topic is the unit of learning. Each topic has one or more language versions,
 * and every version is reviewed by a speaker of that language before students
 * ever see it — an unreviewed translation is more dangerous than no translation,
 * because the learner cannot tell it is wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();        // en, af, zu, xh, st …
            $table->string('name');                       // English name
            $table->string('native_name');                // what speakers call it
            $table->boolean('is_active')->default(true);
            $table->boolean('tts_supported')->default(false);
            $table->string('tts_voice', 64)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_item_id')->constrained()->cascadeOnDelete(); // subject
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->index();
            $table->text('summary')->nullable();
            $table->json('objectives')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['curriculum_item_id', 'is_published']);
        });

        // Source material the lesson is generated from
        Schema::create('topic_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 24);                  // pdf|document|text|voice|link
            $table->string('title')->nullable();
            $table->string('path')->nullable();
            $table->string('external_url')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->boolean('rights_declared')->default(false);
            $table->string('extraction_status', 16)->default('pending'); // pending|done|failed
            $table->timestamps();
        });

        Schema::create('topic_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('draft'); // draft|generating|review|published|rejected
            $table->json('lesson')->nullable();             // segments, examples, diagrams
            $table->longText('notes')->nullable();          // one-page revision sheet
            $table->json('flashcards')->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('model', 64)->nullable();
            $table->decimal('cost_usd', 8, 5)->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['topic_id', 'language_id']);
        });

        Schema::create('topic_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_version_id')->constrained()->cascadeOnDelete();
            $table->string('level', 24);                 // basic|easy|intermediate|difficult|extreme
            $table->text('question');
            $table->json('options');                     // each: text + whether correct + misconception note
            $table->unsignedTinyInteger('correct_index');
            $table->text('explanation');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['topic_version_id', 'level']);
        });
    }

    public function down(): void
    {
        foreach (['topic_questions', 'topic_versions', 'topic_sources', 'topics', 'languages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
