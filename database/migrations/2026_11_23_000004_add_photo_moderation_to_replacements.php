<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 3 (owner-approved addition): genuine image-content moderation for
 * member-submitted photograph replacements (OpenAI omni-moderation).
 * Moderation is a screening stage — flagged or uncertain submissions always
 * require manual examination by the Security Administrator before approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_photo_replacements', function (Blueprint $table): void {
            $table->string('moderation_result', 16)->default('uncertain')->after('screening_result');
            $table->json('moderation_categories')->nullable()->after('moderation_result');
            $table->string('moderation_error', 64)->nullable()->after('moderation_categories');
            $table->timestamp('moderated_at')->nullable()->after('moderation_error');
        });
    }

    public function down(): void
    {
        Schema::table('pending_photo_replacements', function (Blueprint $table): void {
            $table->dropColumn(['moderation_result', 'moderation_categories', 'moderation_error', 'moderated_at']);
        });
    }
};
