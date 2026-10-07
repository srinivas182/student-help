<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monetisation and payments.
 *
 * Plans and prices live in the database so DX can change them without a
 * release, and existing users can be grandfathered rather than suddenly billed
 * for something that was free when they joined.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);   // ZAR cents
            $table->string('interval', 16)->default('month');     // month|year|once
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('monthly_request_quota')->nullable(); // null = unlimited
            $table->unsignedSmallInteger('monthly_ai_quota')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('pending');     // pending|active|cancelled|expired|failed
            $table->string('provider', 24)->default('payfast');
            $table->string('provider_reference')->nullable();
            $table->string('provider_token')->nullable();         // recurring token
            $table->unsignedInteger('amount_cents');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 24)->default('payfast');
            $table->string('payment_id')->nullable()->index();    // provider's id
            $table->string('merchant_reference')->unique();        // ours
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('ZAR');
            $table->string('status', 24)->default('pending');     // pending|complete|failed|cancelled
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'subscriptions', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
