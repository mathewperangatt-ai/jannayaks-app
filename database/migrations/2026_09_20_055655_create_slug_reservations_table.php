<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slug_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->foreignId('profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->string('source', 32)->default('assigned');
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS slug_reservations_slug_lower_unique ON slug_reservations (LOWER(slug))');
        }

        $this->backfillExistingSlugs();
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS slug_reservations_slug_lower_unique');
        }

        Schema::dropIfExists('slug_reservations');
    }

    private function backfillExistingSlugs(): void
    {
        $now = now();

        foreach (DB::table('profiles')->whereNotNull('slug')->orderBy('id')->cursor() as $row) {
            DB::table('slug_reservations')->insertOrIgnore([
                'slug' => $row->slug,
                'profile_id' => $row->id,
                'application_id' => null,
                'source' => 'assigned',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('in_memoriam_profiles')->whereNotNull('slug')->orderBy('id')->cursor() as $row) {
            DB::table('slug_reservations')->insertOrIgnore([
                'slug' => $row->slug,
                'profile_id' => null,
                'application_id' => null,
                'source' => 'in_memoriam',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('slug_redirects')->orderBy('id')->cursor() as $row) {
            DB::table('slug_reservations')->insertOrIgnore([
                'slug' => $row->old_slug,
                'profile_id' => $row->redirectable_type === 'App\\Models\\Profile' ? $row->redirectable_id : null,
                'application_id' => null,
                'source' => 'historical',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
