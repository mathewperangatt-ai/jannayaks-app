<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $stateName = 'Keralam';
        $countryCode = 'IN';
        $now = now();

        DB::table('geo_states')
            ->where('name', 'Kerala')
            ->update([
                'name' => $stateName,
                'updated_at' => $now,
            ]);

        $this->normalizeExistingStateNames('profile_geographies', $stateName, $now);
        $this->normalizeExistingStateNames('in_memoriam_geographies', $stateName, $now);
        $this->backfillMissingLivingProfileGeographies($stateName, $countryCode, $now);

        Schema::table('profile_geographies', function (Blueprint $table) use ($stateName): void {
            $table->string('state_region_name', 255)->default($stateName)->nullable(false)->change();
        });

        Schema::table('in_memoriam_geographies', function (Blueprint $table) use ($stateName): void {
            $table->string('state_region_name', 255)->default($stateName)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('profile_geographies', function (Blueprint $table): void {
            $table->string('state_region_name', 255)->nullable()->default(null)->change();
        });

        Schema::table('in_memoriam_geographies', function (Blueprint $table): void {
            $table->string('state_region_name', 255)->nullable()->default(null)->change();
        });

        $now = now();
        DB::table('geo_states')
            ->where('name', 'Keralam')
            ->update([
                'name' => 'Kerala',
                'updated_at' => $now,
            ]);

        DB::table('profile_geographies')
            ->where('state_region_name', 'Keralam')
            ->update([
                'state_region_name' => 'Kerala',
                'updated_at' => $now,
            ]);

        DB::table('in_memoriam_geographies')
            ->where('state_region_name', 'Keralam')
            ->update([
                'state_region_name' => 'Kerala',
                'updated_at' => $now,
            ]);
    }

    private function normalizeExistingStateNames(string $table, string $stateName, mixed $now): void
    {
        DB::table($table)
            ->where(function ($query): void {
                $query->whereNull('state_region_name')
                    ->orWhere('state_region_name', '')
                    ->orWhere('state_region_name', 'Kerala');
            })
            ->update([
                'state_region_name' => $stateName,
                'updated_at' => $now,
            ]);
    }

    private function backfillMissingLivingProfileGeographies(string $stateName, string $countryCode, mixed $now): void
    {
        $existing = DB::table('profile_geographies')->pluck('profile_id');
        $missingQuery = DB::table('profiles');
        if ($existing->isNotEmpty()) {
            $missingQuery->whereNotIn('id', $existing);
        }
        $missing = $missingQuery->pluck('id');

        foreach ($missing as $profileId) {
            DB::table('profile_geographies')->insert([
                'profile_id' => $profileId,
                'country_code' => $countryCode,
                'state_region_name' => $stateName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
