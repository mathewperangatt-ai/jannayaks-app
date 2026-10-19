<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Visitors may contact a profile owner by email alone; the mobile number
     * is no longer a required collection field.
     */
    public function up(): void
    {
        Schema::table('profile_contact_messages', function (Blueprint $table): void {
            $table->string('visitor_mobile', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('profile_contact_messages', function (Blueprint $table): void {
            $table->string('visitor_mobile', 32)->nullable(false)->change();
        });
    }
};
