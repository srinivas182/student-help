<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and permissions.
 *
 * The base role on the users table still decides which portal someone belongs
 * to. These roles sit on top and decide what they may do — so DX can create a
 * Content Reviewer who can approve lessons but cannot touch users, settings or
 * anybody's private conversations.
 *
 * Reviewers are scoped: a reviewer may be limited to particular subjects and
 * particular languages, because approving an isiZulu Physical Sciences lesson
 * needs someone who has both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->json('permissions');
            $table->boolean('is_system')->default(false); // cannot be deleted
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'user_id']);
        });

        // Which subjects and languages a reviewer may sign off
        Schema::create('reviewer_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'curriculum_item_id', 'language_id'], 'reviewer_scope_unique');
        });
    }

    public function down(): void
    {
        foreach (['reviewer_scopes', 'role_user', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
