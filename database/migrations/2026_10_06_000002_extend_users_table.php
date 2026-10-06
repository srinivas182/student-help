<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity, roles and guardian consent (SRS: AUTH-*, CON-*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('id')->nullable();
            $table->string('last_name')->after('first_name')->nullable();
            $table->date('date_of_birth')->nullable()->after('email');
            $table->string('role', 24)->default('student')->index()->after('date_of_birth');
            $table->string('status', 24)->default('active')->index()->after('role');
            $table->string('mobile', 32)->nullable()->after('status');
            $table->timestamp('mobile_verified_at')->nullable()->after('mobile');

            // Academic context (CON-03 gates access until consent is approved)
            $table->foreignId('institution_id')->nullable()->after('mobile_verified_at')->constrained()->nullOnDelete();
            $table->timestamp('onboarding_completed_at')->nullable();

            // Monetisation engine (admin-controlled; free for everyone at launch)
            $table->string('plan', 24)->default('free');
            $table->timestamp('plan_expires_at')->nullable();
            $table->boolean('free_for_life')->default(false);
            $table->unsignedInteger('monthly_request_quota')->nullable(); // null = unlimited

            $table->softDeletes();
        });

        Schema::create('guardian_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('guardian_name');
            $table->string('guardian_email');
            $table->string('guardian_mobile', 32)->nullable();
            $table->string('token', 64)->unique();
            $table->string('status', 16)->default('pending')->index(); // pending|approved|declined|withdrawn
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_ip', 45)->nullable();
            $table->string('policy_version', 16)->nullable();
            $table->timestamps();
        });

        // Student's chosen academic context: pathway, institution type, grade, faculty, subjects
        Schema::create('academic_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_item_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('context'); // context|subject
            $table->timestamps();

            $table->unique(['user_id', 'curriculum_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_selections');
        Schema::dropIfExists('guardian_consents');
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn([
                'first_name', 'last_name', 'date_of_birth', 'role', 'status', 'mobile',
                'mobile_verified_at', 'onboarding_completed_at', 'plan', 'plan_expires_at',
                'free_for_life', 'monthly_request_quota',
            ]);
        });
    }
};
