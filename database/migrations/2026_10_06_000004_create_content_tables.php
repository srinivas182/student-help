<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Learning resources and announcements (SRS: RES-*, ANN-*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('resource_type', 32);     // notes|course_material|past_paper|video|other
            $table->string('path')->nullable();
            $table->string('external_url')->nullable();
            $table->string('mime_type', 96)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->string('status', 16)->default('pending')->index(); // pending|published|rejected|unpublished
            $table->boolean('rights_declared')->default(false);        // copyright declaration
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();
        });

        Schema::create('resource_curriculum_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_item_id')->constrained()->cascadeOnDelete();
            $table->unique(['resource_id', 'curriculum_item_id'], 'resource_curriculum_unique');
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('priority', 16)->default('normal'); // normal|important
            $table->json('targeting')->nullable();             // roles + curriculum item ids
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('resource_curriculum_item');
        Schema::dropIfExists('resources');
    }
};
