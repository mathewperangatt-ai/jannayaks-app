<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day 1 of the ten-calendar-day Online Interview submission period is the day
 * the customer first opens the questionnaire, which is not the application
 * creation time held in intake_started_at. Additive and nullable: existing
 * rows keep null until the questionnaire is next opened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->timestamp('online_interview_started_at')->nullable()->after('intake_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('online_interview_started_at');
        });
    }
};
