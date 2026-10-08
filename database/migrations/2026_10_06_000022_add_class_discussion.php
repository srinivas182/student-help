<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets students speak in a class.
 *
 * Until now only the teacher could post, so a learner who joined had no way to
 * ask anything — which makes a class a broadcast, not a class.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_posts', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('classroom_id')
                ->constrained('classroom_posts')->cascadeOnDelete();
            // Contact details are stripped from student posts, as everywhere
            // else; moderators see the original
            $table->text('body_original')->nullable()->after('body');
            $table->boolean('is_flagged')->default(false)->after('body_original');
            $table->boolean('is_removed')->default(false)->after('is_flagged');
            $table->unsignedInteger('replies_count')->default(0)->after('is_removed');

            $table->index(['classroom_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('classroom_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['body_original', 'is_flagged', 'is_removed', 'replies_count']);
        });
    }
};
