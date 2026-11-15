<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: the customer approves the exact publication-ready presentation.
 * The sha256 fingerprint of the presentation inputs (profile fields,
 * offices, approved photograph set, video links, editorial ids) recorded at
 * approval time lets publication refuse an approval whose presentation has
 * since changed. Null for approvals recorded before this column existed —
 * they keep their existing behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_customer_approvals', function (Blueprint $table) {
            $table->string('presentation_fingerprint', 64)->nullable()->after('consent_scope');
        });
    }

    public function down(): void
    {
        Schema::table('editorial_customer_approvals', function (Blueprint $table) {
            $table->dropColumn('presentation_fingerprint');
        });
    }
};
