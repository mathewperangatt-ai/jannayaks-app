<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: editorial photograph placements. One row per profile+medium
 * recording WHERE the photograph appears in the publication-ready
 * presentation (hero / inline / gallery), with a stable section anchor
 * (biography / career / gallery — never a paragraph number, so the
 * placement survives bilingual and revised paragraph structures), a
 * display order, optional per-language captions and approval state.
 *
 * The placement never touches the underlying MediaItem: photographs are
 * only ever repositioned, and removing a placement row leaves the asset,
 * its approval state and its storage untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_photo_placements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('media_item_id')->constrained('media_items')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->string('placement_type', 16); // hero | inline | gallery
            $table->string('section_anchor', 32)->nullable(); // biography | career | gallery
            $table->unsignedInteger('display_order')->default(1);
            $table->text('caption_en')->nullable();
            $table->text('caption_ml')->nullable();
            $table->string('status', 16)->default('suggested'); // suggested | approved
            $table->string('source', 12)->default('ai'); // ai | admin
            $table->foreignId('placement_run_id')->nullable()->constrained('photo_placement_runs')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('ai_rationale')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'media_item_id'], 'photo_placements_one_per_medium');
            $table->index(['profile_id', 'status', 'placement_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_photo_placements');
    }
};
