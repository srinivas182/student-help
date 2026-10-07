<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-factor authentication, gateways and one-time codes.
 *
 * Recovery codes are the real backup, not email: if someone's inbox is
 * compromised, an emailed code protects nothing. Removing 2FA from a user is a
 * time-limited bypass rather than a permanent switch, so a single compromised
 * admin account cannot quietly unprotect the platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('two_factor_bypass_until')->nullable()->after('two_factor_confirmed_at');
            $table->string('two_factor_bypass_reason')->nullable()->after('two_factor_bypass_until');
        });

        Schema::create('one_time_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);          // login|generation
            $table->string('channel', 16);          // email|sms
            $table->string('code_hash');
            $table->json('payload')->nullable();    // what the code authorises
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose']);
        });

        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash');
            $table->string('label')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('channel', 16);          // email|sms
            $table->string('name');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'two_factor_bypass_until', 'two_factor_bypass_reason',
            ]);
        });

        foreach (['message_templates', 'trusted_devices', 'one_time_codes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
