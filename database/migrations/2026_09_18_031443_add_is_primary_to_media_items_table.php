<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('display_order');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // At most one primary profile photograph per mediable owner.
            DB::statement('
                CREATE UNIQUE INDEX media_items_one_primary_profile_photo_idx
                ON media_items (mediable_type, mediable_id)
                WHERE is_primary = true AND media_type = \'profile_photo\'
            ');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS media_items_one_primary_profile_photo_idx');
        }

        Schema::table('media_items', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
