<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_body_id')
                ->constrained('geo_local_bodies')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->string('ward_code', 32);
            $table->string('name', 128);
            $table->unsignedInteger('pop_male')->nullable();
            $table->unsignedInteger('pop_female')->nullable();
            $table->unsignedInteger('pop_other')->nullable();
            $table->unsignedInteger('pop_total')->nullable();
            $table->timestamps();

            $table->unique('ward_code');
            $table->unique(['local_body_id', 'name']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_wards');
    }
};
