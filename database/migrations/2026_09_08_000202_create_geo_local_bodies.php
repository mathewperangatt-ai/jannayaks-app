<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_local_bodies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')
                ->constrained('geo_districts')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->string('body_code', 32);
            $table->string('type', 32);
            $table->string('name', 128);
            $table->unsignedInteger('ward_count')->default(0);
            $table->timestamps();

            $table->unique('body_code');
            $table->unique(['district_id', 'type', 'name']);
            $table->index('type');
            $table->index('name');
        });

        DB::statement("
            ALTER TABLE geo_local_bodies
            ADD CONSTRAINT geo_local_bodies_type_check
            CHECK (type IN (
                'grama_panchayat',
                'block_panchayat',
                'district_panchayat',
                'municipality',
                'municipal_corporation'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE geo_local_bodies DROP CONSTRAINT IF EXISTS geo_local_bodies_type_check');
        Schema::dropIfExists('geo_local_bodies');
    }
};
