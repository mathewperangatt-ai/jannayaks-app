<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: provenance and cost-control record for AI photograph-placement
 * recommendations. Runs are started only by an explicit administrator
 * action; the input fingerprint (photo set + editorial version) lets the
 * service reuse an identical previous run instead of paying for repeated
 * analysis of unchanged content. Failures are recorded without damaging
 * the profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_placement_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('status', 16)->default('running'); // running | succeeded | failed
            $table->string('provider', 24)->default('gpt');
            $table->string('model', 64)->nullable();
            $table->string('input_fingerprint', 64)->index();
            $table->foreignId('english_editorial_content_id')->nullable()->constrained('editorial_contents')->nullOnDelete();
            $table->json('recommendations')->nullable();
            $table->json('warnings')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('application_id');
        });

        // At most one in-flight placement run per application (PostgreSQL-safe
        // concurrency guard, same pattern as ai_editorial_runs).
        DB::statement("
            CREATE UNIQUE INDEX photo_placement_runs_one_running_per_application
            ON photo_placement_runs (application_id)
            WHERE status = 'running'
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS photo_placement_runs_one_running_per_application');
        Schema::dropIfExists('photo_placement_runs');
    }
};
