<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_states', function (Blueprint $table) {
            $table->char('code', 2)->primary();
            $table->string('name', 128);
            $table->string('country_name', 128)->default('India');
            $table->timestamps();

            $table->unique(['country_name', 'name']);
        });

        Schema::create('geo_districts', function (Blueprint $table) {
            $table->id();
            $table->char('state_code', 2);
            $table->string('name', 128);
            $table->string('source_code', 64)->nullable()->comment('Uppercase district key from CSV/JSON source');
            $table->timestamps();

            $table->foreign('state_code')
                ->references('code')->on('geo_states')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->unique(['state_code', 'name']);
            $table->index('source_code');
            $table->index('state_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_districts');
        Schema::dropIfExists('geo_states');
    }
};
