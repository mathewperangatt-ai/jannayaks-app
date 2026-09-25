<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SHA-256 (hex, 64 chars) of the EXACT stored JPEG bytes for profile
        // photographs. Nullable: legacy rows are backfilled by the
        // `jannayaks:backfill-photo-hashes` command, never by this migration.
        Schema::table('media_items', function (Blueprint $table) {
            $table->char('photo_sha256', 64)->nullable()->after('size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropColumn('photo_sha256');
        });
    }
};
