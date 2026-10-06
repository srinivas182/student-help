<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student-created study groups.
 *
 * Unlike a class, there is no verified adult responsible for the space — it is
 * minors talking to minors. So groups carry the same message filtering and
 * moderator visibility as one-to-one chat, plus a size cap and an automatic
 * flag when reports accumulate, so a problem group surfaces by itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('curriculum_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->string('join_code', 12)->unique();
            $table->boolean('is_open')->default(true);
            $table->unsignedSmallInteger('capacity')->default(30);
            $table->unsignedTinyInteger('report_count')->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->string('locked_reason')->nullable();
            $table->timestamps();

            $table->index(['is_flagged', 'is_locked']);
        });

        Schema::create('study_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member');   // owner|member
            $table->string('status', 16)->default('active'); // active|removed|left
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['study_group_id', 'user_id']);
        });

        Schema::create('study_group_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');            // masked, shown to members
            $table->text('body_original');   // moderator only
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_removed')->default(false);
            $table->timestamps();

            $table->index(['study_group_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['study_group_messages', 'study_group_members', 'study_groups'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
