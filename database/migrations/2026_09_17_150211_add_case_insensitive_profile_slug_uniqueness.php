<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Case-insensitive uniqueness for living profile slugs (PostgreSQL).
        // Application code always normalises, but concurrent case-variant writes need DB enforcement.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS profiles_slug_lower_unique ON profiles (LOWER(slug)) WHERE slug IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS slug_redirects_old_slug_lower_unique ON slug_redirects (LOWER(old_slug))');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS profiles_slug_lower_unique');
            DB::statement('DROP INDEX IF EXISTS slug_redirects_old_slug_lower_unique');
        }
    }
};
