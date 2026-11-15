<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: structured country of residence (ISO 3166-1 alpha-2) on the
 * application. It determines the pricing category (India INR / other USD),
 * is captured at intake, correctable by administrators, and never inferred
 * from IP address, telephone country code or browser locale.
 *
 * Existing rows stay NULL: they are not auto-classified as Indian
 * residents. Null keeps the pre-existing INR pricing behaviour for legacy
 * applications until an administrator records a country.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('country_of_residence', 2)
                ->nullable()
                ->after('broad_location');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn('country_of_residence');
        });
    }
};
