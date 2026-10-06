<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI study assistant.
 *
 * Every answer costs DX money, so usage is metered at three levels: per student
 * per month, per student per day, and a platform-wide monthly spend cap. The
 * platform cap is the one that protects the business — per-student limits do
 * nothing if two thousand students each use their full allowance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('help_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->text('answer');
            $table->string('provider', 32);
            $table->string('model', 64);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 8, 5)->default(0);
            $table->boolean('refused')->default(false);     // assessment-completion requests
            $table->boolean('escalated')->default(false);   // handed to a human
            $table->unsignedTinyInteger('helpful')->nullable(); // 1 helpful, 0 not
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('monthly_ai_quota')->nullable()->after('monthly_request_quota');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('monthly_ai_quota'));
        Schema::dropIfExists('ai_answers');
    }
};
