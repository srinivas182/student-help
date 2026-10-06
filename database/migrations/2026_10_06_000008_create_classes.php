<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teacher-hosted classes.
 *
 * One model, two kinds. A "personal" class is the teacher's own group and works
 * immediately. A "school" class carries an institution's name, so it only shows
 * that name once DX has approved the link — otherwise anyone could trade on a
 * real school's reputation to gather learners.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 16)->default('personal');          // personal|school
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('school_link_status', 16)->nullable();      // pending|approved|rejected
            $table->text('school_link_notes')->nullable();
            $table->foreignId('curriculum_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('join_code', 12)->unique();
            $table->boolean('join_code_active')->default(true);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['teacher_id', 'is_archived']);
        });

        Schema::create('classroom_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('active');           // active|removed|left
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['classroom_id', 'user_id']);
        });

        // Material and notices shared with one class
        Schema::create('classroom_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16)->default('note');               // note|task
            $table->string('title');
            $table->text('body');
            $table->foreignId('resource_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();

            $table->index(['classroom_id', 'created_at']);
        });

        Schema::create('classroom_post_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');

            $table->unique(['classroom_post_id', 'user_id'], 'post_completion_unique');
        });
    }

    public function down(): void
    {
        foreach (['classroom_post_completions', 'classroom_posts', 'classroom_members', 'classrooms'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
