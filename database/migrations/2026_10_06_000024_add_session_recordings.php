<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recordings, materials and reminders for class sessions.
 *
 * Recordings are stored as a link by default rather than a file. An hour of
 * video is several hundred megabytes: it would fill the server within a term,
 * and a learner on mobile data cannot afford to stream it from us. Zoom Cloud,
 * Drive and unlisted YouTube host it free and adapt quality to the connection.
 * Upload stays available for a tutor with no cloud option.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->string('recording_url')->nullable()->after('meeting_url');
            $table->string('recording_path')->nullable()->after('recording_url');
            $table->string('recording_note')->nullable()->after('recording_path');
            $table->timestamp('recording_added_at')->nullable()->after('recording_note');
            // Reminders fire once; this records that they did
            $table->timestamp('reminded_at')->nullable()->after('recording_added_at');
            $table->timestamp('started_at')->nullable()->after('reminded_at');
        });

        Schema::create('class_session_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('path')->nullable();
            $table->string('external_url')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index('class_session_id');
        });

        Schema::table('class_session_attendance', function (Blueprint $table) {
            // How long they were actually in the room, not just that they clicked
            $table->timestamp('left_at')->nullable()->after('attended_at');
            $table->boolean('watched_recording')->default(false)->after('left_at');
        });
    }

    public function down(): void
    {
        Schema::table('class_session_attendance', function (Blueprint $table) {
            $table->dropColumn(['left_at', 'watched_recording']);
        });

        Schema::dropIfExists('class_session_materials');

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'recording_url', 'recording_path', 'recording_note',
                'recording_added_at', 'reminded_at', 'started_at',
            ]);
        });
    }
};
