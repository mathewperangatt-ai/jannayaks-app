<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserved_slugs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->string('category', 32)->default('other');
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS reserved_slugs_slug_lower_unique ON reserved_slugs (LOWER(slug))');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS reserved_slugs_slug_lower_unique');
        }

        Schema::dropIfExists('reserved_slugs');
    }
};
