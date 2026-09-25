<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Known-good snapshot of the PUBLIC state of each published profile.
        // Represents only publicly rendered data (identity, pinned editorial
        // versions, approved public photographs, public offices, video links).
        // Deliberately excludes private contact, consent, notes, and
        // administrative metadata.
        Schema::create('profile_integrity_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                ->unique()
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->foreignId('application_id')
                ->nullable()
                ->constrained('applications')
                ->nullOnDelete();

            // Identity
            $table->string('slug')->nullable();
            $table->string('display_name')->nullable();
            $table->string('profession')->nullable();

            // Pinned editorial versions (exact public rendering source)
            $table->unsignedBigInteger('english_editorial_content_id')->nullable();
            $table->char('english_content_hash', 64)->nullable();
            $table->unsignedBigInteger('malayalam_editorial_content_id')->nullable();
            $table->char('malayalam_content_hash', 64)->nullable();

            // Primary approved public photograph
            $table->unsignedBigInteger('primary_photo_media_id')->nullable();
            $table->string('primary_photo_disk', 32)->nullable();
            $table->text('primary_photo_key')->nullable();
            $table->char('primary_photo_sha256', 64)->nullable();
            $table->unsignedBigInteger('primary_photo_size_bytes')->nullable();
            // Full ordered approved-public photo set (deterministic ordering),
            // each entry: {media_id, disk, key, sha256, size_bytes, is_primary}
            $table->jsonb('photos');

            // Public collections (deterministic ordering)
            $table->jsonb('public_offices');
            $table->jsonb('video_links');

            // Tamper evidence over the canonical payload. This is NOT a
            // cryptographic root of trust: an attacker with raw database write
            // access can recompute it. It cheaply detects accidental or partial
            // tampering that does not bother to reseal the row.
            $table->char('snapshot_hash', 64);

            $table->string('source_event', 96)->nullable();
            $table->timestampTz('verified_at', 0)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_integrity_snapshots');
    }
};
