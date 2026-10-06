<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subject discussion boards, progress tracking and tutor recognition.
 *
 * Boards are scoped to a curriculum subject, so a Grade 9 learner never lands
 * in a university thread. Moderation reuses the filter and report pipeline
 * already built for private messages and study groups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('community_posts')->cascadeOnDelete();
            $table->string('title')->nullable();     // questions only
            $table->text('body');                    // masked
            $table->text('body_original');           // moderator only
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_removed')->default(false);
            $table->boolean('is_accepted')->default(false);
            $table->unsignedInteger('votes')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();

            $table->index(['curriculum_item_id', 'parent_id', 'created_at']);
        });

        Schema::create('community_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['community_post_id', 'user_id']);
        });

        // One row per active day, which is all a streak needs
        Schema::create('activity_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedSmallInteger('actions')->default(1);

            $table->unique(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        foreach (['activity_days', 'community_votes', 'community_posts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
