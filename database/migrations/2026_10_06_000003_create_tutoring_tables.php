<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tutor profiles, verification, help requests, matching and messaging
 * (SRS: TUT-*, REQ-*, MSG-*, RAT-*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->string('highest_qualification')->nullable();
            $table->string('institution_name')->nullable();
            $table->json('languages')->nullable();
            $table->json('availability')->nullable();
            $table->string('verification_status', 16)->default('pending')->index(); // pending|approved|rejected
            $table->text('verification_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_available')->default(true);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('resolved_count')->default(0);
            $table->timestamps();
        });

        Schema::create('tutor_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tutor_profile_id', 'curriculum_item_id'], 'tutor_subject_unique');
        });

        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 48);   // id|qualification|police_clearance
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size');
            $table->timestamps();
        });

        Schema::create('help_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tutor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_id')->constrained('curriculum_items')->cascadeOnDelete();
            $table->string('topic');
            $table->text('description');
            $table->string('status', 16)->default('open')->index(); // open|escalated|assigned|resolved|closed|cancelled
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['subject_id', 'status']);
        });

        Schema::create('help_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 96);
            $table->unsignedInteger('size');
            $table->timestamps();
        });

        // Tutors invited to a request, so declines are never re-offered (REQ-05)
        Schema::create('help_request_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->default('offered'); // offered|accepted|declined
            $table->string('decline_reason')->nullable();
            $table->timestamps();

            $table->unique(['help_request_id', 'tutor_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');            // masked version shown to users (MSG-03)
            $table->text('body_original');   // unmasked, moderator-only, audit-logged on access
            $table->boolean('is_flagged')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['help_request_id', 'created_at']);
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->string('comment', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reportable_type');
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason', 64);
            $table->text('notes')->nullable();
            $table->string('severity', 16)->default('normal')->index(); // normal|high (minor involved)
            $table->string('status', 16)->default('open')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('outcome')->nullable();
            $table->timestamps();

            $table->index(['reportable_type', 'reportable_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 96)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('context')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs', 'reports', 'ratings', 'messages', 'help_request_offers',
            'help_request_attachments', 'help_requests', 'verification_documents',
            'tutor_subjects', 'tutor_profiles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
