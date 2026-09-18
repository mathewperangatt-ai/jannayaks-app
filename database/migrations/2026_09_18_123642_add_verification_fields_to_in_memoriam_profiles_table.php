<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('in_memoriam_profiles', function (Blueprint $table) {
            $table->string('verification_status', 32)->default('unverified')->after('deceased_place_of_death');
            $table->string('verification_method', 64)->nullable()->after('verification_status');
            $table->timestamp('verified_at', 0)->nullable()->after('verification_method');
            $table->text('verification_notes')->nullable()->after('verified_at');
        });

        DB::statement("
            ALTER TABLE in_memoriam_profiles
            ADD CONSTRAINT in_memoriam_profiles_verification_status_check
            CHECK (verification_status IN (
                'unverified',
                'verified',
                'could_not_verify',
                'waived'
            ))
        ");

        DB::statement("
            ALTER TABLE in_memoriam_profiles
            ADD CONSTRAINT in_memoriam_profiles_verification_method_check
            CHECK (
                verification_method IS NULL
                OR verification_method IN (
                    'death_certificate_inspection',
                    'official_records',
                    'both',
                    'other'
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE in_memoriam_profiles DROP CONSTRAINT IF EXISTS in_memoriam_profiles_verification_method_check');
        DB::statement('ALTER TABLE in_memoriam_profiles DROP CONSTRAINT IF EXISTS in_memoriam_profiles_verification_status_check');

        Schema::table('in_memoriam_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'verification_status',
                'verification_method',
                'verified_at',
                'verification_notes',
            ]);
        });
    }
};
