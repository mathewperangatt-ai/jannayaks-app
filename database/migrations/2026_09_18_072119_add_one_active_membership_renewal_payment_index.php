<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // At most one pending/initiated membership renewal attempt per membership (PostgreSQL).
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS payments_one_active_attempt_per_membership
            ON payments (membership_id)
            WHERE membership_id IS NOT NULL
              AND item_type = 'membership'
              AND status IN ('pending', 'initiated')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payments_one_active_attempt_per_membership');
    }
};
