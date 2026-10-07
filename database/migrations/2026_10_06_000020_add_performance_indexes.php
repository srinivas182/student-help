<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the queries that run most often.
 *
 * Each one matches a real query in the app rather than a guess: the tutor
 * queue, a student's request list, the moderation queue, and the curriculum
 * lookups that run on every onboarding step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_requests', function (Blueprint $table) {
            // Tutor queue: open requests in a subject, newest first
            $table->index(['status', 'subject_id', 'created_at'], 'hr_status_subject_created');
            // A student's own list
            $table->index(['student_id', 'status'], 'hr_student_status');
            // A tutor's active work
            $table->index(['tutor_id', 'status'], 'hr_tutor_status');
            // Escalation sweep
            $table->index(['status', 'created_at'], 'hr_status_created');
        });

        Schema::table('curriculum_items', function (Blueprint $table) {
            // Onboarding walks the tree by parent and type constantly
            $table->index(['parent_id', 'type', 'is_active'], 'ci_parent_type_active');
        });

        Schema::table('academic_selections', function (Blueprint $table) {
            $table->index(['user_id', 'role'], 'as_user_role');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['help_request_id', 'created_at'], 'msg_request_created');
        });

        Schema::table('resources', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'res_status_created');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['action', 'created_at'], 'audit_action_created');
            $table->index(['actor_id', 'created_at'], 'audit_actor_created');
        });
    }

    public function down(): void
    {
        Schema::table('help_requests', function (Blueprint $table) {
            $table->dropIndex('hr_status_subject_created');
            $table->dropIndex('hr_student_status');
            $table->dropIndex('hr_tutor_status');
            $table->dropIndex('hr_status_created');
        });

        Schema::table('curriculum_items', fn (Blueprint $t) => $t->dropIndex('ci_parent_type_active'));
        Schema::table('academic_selections', fn (Blueprint $t) => $t->dropIndex('as_user_role'));
        Schema::table('messages', fn (Blueprint $t) => $t->dropIndex('msg_request_created'));
        Schema::table('resources', fn (Blueprint $t) => $t->dropIndex('res_status_created'));

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_action_created');
            $table->dropIndex('audit_actor_created');
        });
    }
};
