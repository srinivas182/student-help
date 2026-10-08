<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled sessions for a class.
 *
 * A class with no schedule is a noticeboard: a teacher could create one and a
 * learner had no way to know when anything happened. Sessions give a date, a
 * time, a place — online with a link, or a physical room — and attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('mode', 16)->default('online');     // online|in_person
            $table->string('meeting_url')->nullable();          // Zoom, Meet, Teams
            $table->string('location')->nullable();             // room or address
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('status', 16)->default('scheduled'); // scheduled|cancelled|done
            $table->string('cancel_reason')->nullable();
            // A simple weekly repeat covers how school timetables actually work
            $table->string('repeats', 16)->nullable();          // null|weekly
            $table->date('repeats_until')->nullable();
            $table->foreignId('parent_session_id')->nullable()->constrained('class_sessions')->nullOnDelete();
            $table->timestamps();

            $table->index(['classroom_id', 'starts_at']);
            $table->index(['starts_at', 'status']);
        });

        Schema::create('class_session_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('response', 16)->default('going');  // going|not_going
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();

            $table->unique(['class_session_id', 'user_id']);
        });

        Schema::table('classrooms', function (Blueprint $table) {
            // Lets students find and request a class instead of needing a code
            $table->boolean('is_discoverable')->default(false)->after('join_code');
            $table->text('about')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn(['is_discoverable', 'about']);
        });

        Schema::dropIfExists('class_session_attendance');
        Schema::dropIfExists('class_sessions');
    }
};
