<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memorial profiles are prepared and uploaded manually; the commissioner's
     * mobile number is no longer a required collection field. Existing values
     * are preserved unchanged.
     */
    public function up(): void
    {
        Schema::table('in_memoriam_profiles', function (Blueprint $table): void {
            $table->string('commissioner_contact_mobile', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('in_memoriam_profiles', function (Blueprint $table): void {
            $table->string('commissioner_contact_mobile', 32)->nullable(false)->change();
        });
    }
};
