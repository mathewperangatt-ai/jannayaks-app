<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_geographies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                ->unique()
                ->constrained('profiles')
                ->onDelete('cascade');

            $table->char('country_code', 2)->default('IN');
            $table->string('state_region_name', 255)->nullable();

            $table->foreignId('district_id')
                ->nullable()
                ->constrained('geo_districts')
                ->onDelete('restrict');
            $table->foreignId('local_body_id')
                ->nullable()
                ->constrained('geo_local_bodies')
                ->onDelete('restrict');
            $table->foreignId('ward_id')
                ->nullable()
                ->constrained('geo_wards')
                ->onDelete('restrict');

            $table->string('locality_place', 255)->nullable();
            $table->string('postal_code', 32)->nullable();

            $table->timestamps();

            $table->index('country_code');
            $table->index('district_id');
            $table->index('local_body_id');
            $table->index('ward_id');
            $table->index('locality_place');
            $table->index('postal_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_geographies');
    }
};
