<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('location_scope', 16)->nullable()->after('preferred_slug');
            $table->foreignId('location_district_id')->nullable()->after('location_scope')->constrained('geo_districts')->nullOnDelete();
            $table->string('broad_location', 120)->nullable()->after('location_district_id');
            $table->string('location_state', 120)->nullable()->after('broad_location');
            $table->string('location_country', 80)->nullable()->after('location_state');
        });

        Schema::table('profile_geographies', function (Blueprint $table) {
            $table->string('country_name', 80)->nullable()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('profile_geographies', function (Blueprint $table) {
            $table->dropColumn('country_name');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_district_id');
            $table->dropColumn(['location_scope', 'broad_location', 'location_state', 'location_country']);
        });
    }
};
