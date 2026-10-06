<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counts people arriving at the wrong front door.
 *
 * If students keep landing on the teacher domain, that is a marketing or
 * signage problem, not a user error — so it is worth measuring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_portal', 16);
            $table->string('to_portal', 16);
            $table->string('path', 255)->nullable();
            $table->string('role', 24)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['from_portal', 'to_portal', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_redirects');
    }
};
