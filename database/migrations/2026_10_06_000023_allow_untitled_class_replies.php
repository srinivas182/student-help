<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Replies have no title of their own, so the column must allow null. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_posts', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('classroom_posts', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
