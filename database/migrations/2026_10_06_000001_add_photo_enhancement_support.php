<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI photo enhancement (Phase 2) — minimum schema on the existing
     * MediaItem architecture plus a small provenance table.
     *
     * - media_items.media_type gains 'profile_photo_enhancement': the AI
     *   candidate is a normal MediaItem that existing profile_photo queries
     *   (slot limits, public display, gallery) naturally exclude until an
     *   administrator accepts it.
     * - media_items.enhanced_from_media_id links the candidate to its source.
     * - photo_enhancement_runs records provenance; a partial unique index
     *   enforces ONE active run (queued/processing/completed) per source.
     */
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table): void {
            $table->foreignId('enhanced_from_media_id')->nullable()->after('mediable_id')
                ->constrained('media_items')->nullOnDelete();
        });

        DB::statement('ALTER TABLE media_items DROP CONSTRAINT IF EXISTS media_items_media_type_check');
        DB::statement("
            ALTER TABLE media_items
            ADD CONSTRAINT media_items_media_type_check
            CHECK (media_type IN ('profile_photo','profile_photo_enhancement','gallery_image','document','other'))
        ");

        Schema::create('photo_enhancement_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_media_id')->constrained('media_items')->cascadeOnDelete();
            $table->foreignId('candidate_media_id')->nullable()->constrained('media_items')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('model', 64);
            $table->string('status', 24)->default('queued');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        DB::statement("
            CREATE UNIQUE INDEX photo_enhancement_runs_one_active_per_source
            ON photo_enhancement_runs (source_media_id)
            WHERE status IN ('queued','processing','completed')
        ");
        DB::statement("
            ALTER TABLE photo_enhancement_runs
            ADD CONSTRAINT photo_enhancement_runs_status_check
            CHECK (status IN ('queued','processing','completed','failed','accepted','kept_original','discarded','cancelled'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE photo_enhancement_runs DROP CONSTRAINT IF EXISTS photo_enhancement_runs_status_check');
        Schema::dropIfExists('photo_enhancement_runs');

        DB::statement('ALTER TABLE media_items DROP CONSTRAINT IF EXISTS media_items_media_type_check');
        DB::statement("
            ALTER TABLE media_items
            ADD CONSTRAINT media_items_media_type_check
            CHECK (media_type IN ('profile_photo','gallery_image','document','other'))
        ");

        Schema::table('media_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('enhanced_from_media_id');
        });
    }
};
