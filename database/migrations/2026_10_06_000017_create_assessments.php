<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assessments, mastery and spaced review.
 *
 * The assessment is not a test at the end — it is where the learning happens.
 * Retrieval beats re-reading, and meeting a topic again after 3, 10 and 30 days
 * is what turns "I understood it in class" into "I still know it in November".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_version_id')->constrained()->cascadeOnDelete();
            $table->string('level', 24);
            $table->unsignedTinyInteger('score')->default(0);
            $table->unsignedTinyInteger('total')->default(0);
            $table->unsignedTinyInteger('percent')->default(0);
            $table->boolean('passed')->default(false);
            $table->boolean('is_review')->default(false);   // spaced review, not first pass
            $table->json('answers')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'topic_id', 'level']);
        });

        Schema::create('topic_mastery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('highest_level', 24)->nullable();
            $table->unsignedTinyInteger('best_percent')->default(0);
            $table->unsignedTinyInteger('review_stage')->default(0); // 0 → 3d → 10d → 30d → done
            $table->timestamp('next_review_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'topic_id']);
            $table->index('next_review_at');
        });
    }

    public function down(): void
    {
        foreach (['topic_mastery', 'assessment_attempts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
