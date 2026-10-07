<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-provider credentials, encrypted, with a delivery log.
 *
 * Credentials live here rather than in .env so DX can change provider without a
 * developer, and the log answers the question that always comes up: "did the
 * guardian actually get the consent email?"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);        // email|sms|whatsapp
            $table->string('provider', 32);
            $table->text('credentials');          // encrypted JSON
            $table->boolean('is_active')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'provider']);
        });

        Schema::create('message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            $table->string('provider', 32);
            $table->string('recipient');
            $table->string('purpose', 48)->nullable();
            $table->string('status', 16);         // sent|failed
            $table->string('reference')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['channel', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['message_deliveries', 'gateway_credentials'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
