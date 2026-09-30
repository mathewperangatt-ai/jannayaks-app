<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Internal editorial review flags (Master Editorial Specification §10).
 * AI-emitted, validated against the fixed flag vocabulary, stored per
 * editorial content version. Internal workflow metadata only — never a
 * public badge and never an automatic publication control.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_contents', function (Blueprint $table) {
            $table->jsonb('review_flags')->nullable();
        });

        DB::table('editorial_contents')
            ->whereNull('review_flags')
            ->update(['review_flags' => '[]']);
    }

    public function down(): void
    {
        Schema::table('editorial_contents', function (Blueprint $table) {
            $table->dropColumn('review_flags');
        });
    }
};
