<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrity evidence protection (Wave 3).
 *
 * profile_integrity_snapshots gains an HMAC signature computed with a
 * dedicated secret held only in the environment
 * (JANNAYAKS_INTEGRITY_HMAC_SECRET — deliberately NOT derived from APP_KEY),
 * chained to the previous snapshot's signature so a database-level attacker
 * cannot rewrite a trusted baseline or forge a new one without the secret.
 *
 * Snapshots written before the secret is configured have no signature and
 * remain valid legacy rows; signature verification only applies to rows that
 * carry one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_integrity_snapshots', function (Blueprint $table): void {
            $table->string('signature', 64)->nullable()->after('snapshot_hash');
            $table->string('signature_chain', 64)->nullable()->after('signature');
            $table->index(['profile_id', 'signature']);
        });

        Schema::table('integrity_incidents', function (Blueprint $table): void {
            $table->string('signature', 64)->nullable()->after('detection_fingerprint');
            $table->timestamp('enforced_at')->nullable()->after('detected_at');
        });
    }

    public function down(): void
    {
        Schema::table('integrity_incidents', function (Blueprint $table): void {
            $table->dropColumn(['signature', 'enforced_at']);
        });

        Schema::table('profile_integrity_snapshots', function (Blueprint $table): void {
            $table->dropIndex(['profile_id', 'signature']);
            $table->dropColumn(['signature', 'signature_chain']);
        });
    }
};
