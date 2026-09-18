<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->string('review_status', 32)->default('pending_review')->after('privacy');
            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->after('review_status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->text('review_note')->nullable()->after('reviewed_at');
            $table->index(['mediable_type', 'mediable_id', 'review_status']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE media_items
                ADD CONSTRAINT media_items_review_status_check
                CHECK (review_status IN ('pending_review','approved','rejected'))
            ");

            // Existing rows (e.g. Phase 13 fixtures / pre-P14 test media) treated as already approved
            // when privacy is public; otherwise remain pending.
            DB::statement("
                UPDATE media_items
                SET review_status = CASE
                    WHEN privacy = 'public' THEN 'approved'
                    ELSE 'pending_review'
                END
                WHERE review_status = 'pending_review'
            ");
        } else {
            DB::table('media_items')
                ->where('privacy', 'public')
                ->update(['review_status' => 'approved']);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE media_items DROP CONSTRAINT IF EXISTS media_items_review_status_check');
        }

        Schema::table('media_items', function (Blueprint $table) {
            $table->dropIndex(['mediable_type', 'mediable_id', 'review_status']);
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn(['review_status', 'reviewed_at', 'review_note']);
        });
    }
};
