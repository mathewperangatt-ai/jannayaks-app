<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 3 — user-submitted photograph replacement requests.
 *
 * Members of published profiles may SUBMIT replacement photographs through
 * their existing accounts. Submissions are stored SEPARATELY on private
 * storage and never touch the published R2 photographs or media_items rows.
 * Approval is exclusively a Jannayaks Security Administrator operation, after
 * automated image-safety screening (flagged submissions additionally require
 * explicit manual examination confirmation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_photo_replacements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('member_user_id')->constrained('users')->cascadeOnDelete();

            // Private storage path (private_uploads disk), never the public R2 disk.
            $table->string('storage_path');
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('sha256', 64);
            $table->string('member_note', 1000)->nullable();

            // clear | flagged — flagged submissions require explicit manual
            // examination confirmation before approval is possible.
            $table->string('screening_result', 16)->default('flagged');
            $table->json('screening_reasons')->nullable();
            $table->boolean('manual_examination_confirmed')->default(false);
            $table->unsignedBigInteger('examined_by_user_id')->nullable();

            // submitted | approved | rejected
            $table->string('status', 16)->default('submitted');
            $table->string('review_note', 1000)->nullable();
            $table->unsignedBigInteger('reviewer_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->index(['profile_id', 'status']);
            $table->index(['status', 'screening_result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_photo_replacements');
    }
};
