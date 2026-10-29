<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the public bio_headline identity field to the living-profile integrity
     * snapshot. Existing rows receive NULL: the prior schema never recorded a
     * trusted headline, so live profiles.bio_headline is NOT copied into the
     * baseline (it may already have drifted). Null trusted + non-null live
     * fails closed until an authorised public refreshSnapshot / operator
     * baseline review.
     */
    public function up(): void
    {
        Schema::table('profile_integrity_snapshots', function (Blueprint $table) {
            $table->string('bio_headline', 255)
                ->nullable()
                ->after('profession');
        });
    }

    public function down(): void
    {
        Schema::table('profile_integrity_snapshots', function (Blueprint $table) {
            $table->dropColumn('bio_headline');
        });
    }
};
