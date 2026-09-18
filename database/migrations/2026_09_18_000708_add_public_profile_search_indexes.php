<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Gallery ordering index + LOWER() B-tree helpers for equality/prefix lookups.
        // Limitation: ordinary LOWER() B-tree indexes do not materially accelerate
        // leading-wildcard %ILIKE% searches used by PublicProfileSearchService.
        DB::statement('CREATE INDEX IF NOT EXISTS profiles_published_at_id_idx ON profiles (published_at DESC, id DESC) WHERE status = \'published\' AND published_at IS NOT NULL AND unpublished_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS profiles_full_name_lower_idx ON profiles (LOWER(full_name))');
        DB::statement('CREATE INDEX IF NOT EXISTS profiles_display_name_lower_idx ON profiles (LOWER(display_name))');
        DB::statement('CREATE INDEX IF NOT EXISTS profiles_profession_lower_idx ON profiles (LOWER(profession))');
        DB::statement('CREATE INDEX IF NOT EXISTS profile_geographies_locality_lower_idx ON profile_geographies (LOWER(locality_place))');
        DB::statement('CREATE INDEX IF NOT EXISTS profile_geographies_state_region_lower_idx ON profile_geographies (LOWER(state_region_name))');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS profiles_published_at_id_idx');
        DB::statement('DROP INDEX IF EXISTS profiles_full_name_lower_idx');
        DB::statement('DROP INDEX IF EXISTS profiles_display_name_lower_idx');
        DB::statement('DROP INDEX IF EXISTS profiles_profession_lower_idx');
        DB::statement('DROP INDEX IF EXISTS profile_geographies_locality_lower_idx');
        DB::statement('DROP INDEX IF EXISTS profile_geographies_state_region_lower_idx');
    }
};
