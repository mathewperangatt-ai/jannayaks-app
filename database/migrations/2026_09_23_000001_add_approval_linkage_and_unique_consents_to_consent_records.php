<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_records', function (Blueprint $table) {
            // Link publication-approval consents to the exact application/profile
            // they authorize. Nullable: the interview-processing consent predates
            // this and is user-scoped. Records are preserved (nulled) if the
            // application/profile row ever disappears — the consent evidence stays.
            $table->foreignId('application_id')
                ->nullable()
                ->constrained('applications')
                ->nullOnDelete();
            $table->foreignId('profile_id')
                ->nullable()
                ->constrained('profiles')
                ->nullOnDelete();

            // DB-level duplicate protection scoped to publication-approval consents
            // only: the table's changelog semantics for other consent types
            // (consent → revoke → consent history) are intentionally preserved.
            DB::statement(
                "CREATE UNIQUE INDEX consent_records_publication_approval_unique
                 ON consent_records (user_id, consent_key)
                 WHERE consent_key = 'editorial.approval.publication'"
            );
        });
    }

    public function down(): void
    {
        Schema::table('consent_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('application_id');
            $table->dropConstrainedForeignId('profile_id');
        });

        DB::statement('DROP INDEX IF EXISTS consent_records_publication_approval_unique');
    }
};
