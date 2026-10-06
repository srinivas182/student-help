<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voice note lessons (new scope: spoken explanations from teachers).
 *
 * Audio breaks our text safeguards: masking cannot catch a phone number said
 * out loud. So every voice note is transcribed, the transcript goes through the
 * same content filter, and moderators can read what was said instead of
 * listening to every recording.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // What it is attached to: a class post, a help request, or the library
            $table->string('attachable_type')->nullable();
            $table->unsignedBigInteger('attachable_id')->nullable();

            $table->string('path');
            $table->string('mime_type', 64);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->string('title')->nullable();

            // Safeguarding pipeline
            $table->string('transcription_status', 16)->default('pending'); // pending|done|failed|skipped
            $table->text('transcript')->nullable();          // masked, shown to users
            $table->text('transcript_original')->nullable(); // moderator only
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason', 64)->nullable();
            $table->timestamp('transcribed_at')->nullable();

            $table->unsignedInteger('plays')->default(0);
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index(['is_flagged', 'transcription_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_notes');
    }
};
