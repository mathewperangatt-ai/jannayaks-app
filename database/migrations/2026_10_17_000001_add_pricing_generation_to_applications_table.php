<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('pricing_generation', 32)->nullable()->after('package_tier');
        });

        DB::statement("
            ALTER TABLE applications
            ADD CONSTRAINT applications_pricing_generation_check
            CHECK (pricing_generation IS NULL OR pricing_generation = 'standard_profile')
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS applications_pricing_generation_check');

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('pricing_generation');
        });
    }
};
