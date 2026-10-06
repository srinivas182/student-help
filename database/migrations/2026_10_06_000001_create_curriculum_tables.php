<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curriculum engine (SRS: CUR-01 – CUR-05).
 *
 * A single self-referencing tree holds every academic concept so that DX
 * administrators can change the curriculum without a code release:
 *
 *   pathway → institution_type → track → qualification → level → faculty → subject
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('curriculum_items')->nullOnDelete();
            $table->string('type', 32)->index();     // pathway|institution_type|track|qualification|level|faculty|subject
            $table->string('name');
            $table->string('slug')->index();
            $table->string('code', 32)->nullable();
            $table->string('icon', 64)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'type', 'is_active']);
        });

        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32);              // school|college|university
            $table->string('sector', 16);            // public|private
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index(['type', 'sector', 'province']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
        Schema::dropIfExists('curriculum_items');
    }
};
