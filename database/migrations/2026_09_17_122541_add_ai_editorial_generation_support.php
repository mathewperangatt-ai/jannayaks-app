<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_editorial_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')
                ->constrained('applications')
                ->cascadeOnDelete();
            $table->foreignId('profile_id')
                ->nullable()
                ->constrained('profiles')
                ->nullOnDelete();
            $table->foreignId('requested_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('provider', 32)->default('gpt');
            $table->string('model', 64)->nullable();
            $table->string('status', 32)->default('queued');
            $table->string('stage', 32)->default('english');
            $table->string('package_tier', 32)->nullable();
            $table->string('input_fingerprint', 64)->nullable();
            $table->unsignedBigInteger('english_editorial_content_id')->nullable();
            $table->unsignedBigInteger('malayalam_editorial_content_id')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at', 0)->nullable();
            $table->timestamp('finished_at', 0)->nullable();
            $table->timestamps();

            $table->index(['application_id', 'status']);
            $table->index(['profile_id', 'created_at']);
            $table->index('status');
        });

        // At most one in-flight generation per application (PostgreSQL-safe concurrency guard).
        DB::statement('
            CREATE UNIQUE INDEX ai_editorial_runs_one_running_per_application
            ON ai_editorial_runs (application_id)
            WHERE status = \'running\'
        ');

        DB::statement("
            ALTER TABLE ai_editorial_runs
            ADD CONSTRAINT ai_editorial_runs_status_check
            CHECK (status IN ('queued', 'running', 'succeeded', 'failed'))
        ");

        DB::statement("
            ALTER TABLE ai_editorial_runs
            ADD CONSTRAINT ai_editorial_runs_stage_check
            CHECK (stage IN ('english', 'malayalam', 'claims', 'complete'))
        ");

        Schema::table('editorial_contents', function (Blueprint $table) {
            $table->unsignedBigInteger('source_editorial_content_id')->nullable()->after('profile_id');
            $table->unsignedBigInteger('generation_run_id')->nullable()->after('source_editorial_content_id');
        });

        Schema::table('editorial_contents', function (Blueprint $table) {
            $table->foreign('source_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->nullOnDelete();
            $table->foreign('generation_run_id')
                ->references('id')
                ->on('ai_editorial_runs')
                ->nullOnDelete();
        });

        Schema::table('ai_editorial_runs', function (Blueprint $table) {
            $table->foreign('english_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->nullOnDelete();
            $table->foreign('malayalam_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->nullOnDelete();
        });

        Schema::create('editorial_claim_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_content_id')
                ->constrained('editorial_contents')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('claim_excerpt');
            // AI-supplied pointer only — not independent verification of the claim.
            $table->string('question_id', 32)->nullable();
            $table->foreignId('interview_answer_id')
                ->nullable()
                ->constrained('interview_answers')
                ->nullOnDelete();
            $table->foreignId('source_material_id')
                ->nullable()
                ->constrained('source_materials')
                ->nullOnDelete();
            // True only when interview_answer_id or source_material_id was resolved from source records.
            $table->boolean('mapped_to_source')->default(false);
            $table->timestamps();

            $table->index(['editorial_content_id', 'sort_order']);
            $table->index(['editorial_content_id', 'mapped_to_source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_claim_traces');

        Schema::table('ai_editorial_runs', function (Blueprint $table) {
            $table->dropForeign(['english_editorial_content_id']);
            $table->dropForeign(['malayalam_editorial_content_id']);
        });

        Schema::table('editorial_contents', function (Blueprint $table) {
            $table->dropForeign(['generation_run_id']);
            $table->dropForeign(['source_editorial_content_id']);
            $table->dropColumn(['generation_run_id', 'source_editorial_content_id']);
        });

        DB::statement('DROP INDEX IF EXISTS ai_editorial_runs_one_running_per_application');
        DB::statement('ALTER TABLE ai_editorial_runs DROP CONSTRAINT IF EXISTS ai_editorial_runs_stage_check');
        DB::statement('ALTER TABLE ai_editorial_runs DROP CONSTRAINT IF EXISTS ai_editorial_runs_status_check');
        Schema::dropIfExists('ai_editorial_runs');
    }
};
