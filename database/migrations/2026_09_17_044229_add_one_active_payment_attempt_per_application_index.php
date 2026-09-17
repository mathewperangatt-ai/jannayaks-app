<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // At most one pending/initiated payment attempt per application (PostgreSQL).
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS payments_one_active_attempt_per_application
            ON payments (application_id)
            WHERE application_id IS NOT NULL
              AND status IN ('pending', 'initiated')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payments_one_active_attempt_per_application');
    }
};
