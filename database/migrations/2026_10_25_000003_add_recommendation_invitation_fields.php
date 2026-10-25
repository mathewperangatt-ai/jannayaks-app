<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendations', function (Blueprint $table) {
            $table->string('recommended_email', 255)->nullable()->after('recommended_name');
            $table->boolean('share_recommender_name')->default(false)->after('acknowledged_terms');
            $table->string('consent_notice_version', 64)->nullable()->after('share_recommender_name');
            $table->timestamp('invitation_sent_at')->nullable()->after('notified_at');
            $table->timestamp('invitation_failed_at')->nullable()->after('invitation_sent_at');
            $table->index('recommended_email');
        });
    }

    public function down(): void
    {
        Schema::table('recommendations', function (Blueprint $table) {
            $table->dropIndex(['recommended_email']);
            $table->dropColumn([
                'recommended_email',
                'share_recommender_name',
                'consent_notice_version',
                'invitation_sent_at',
                'invitation_failed_at',
            ]);
        });
    }
};
