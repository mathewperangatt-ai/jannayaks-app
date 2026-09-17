<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedTinyInteger('included_revision_rounds_used')->default(0)->after('status');
            $table->timestamp('customer_preview_released_at', 0)->nullable()->after('included_revision_rounds_used');
            $table->unsignedBigInteger('preview_english_editorial_content_id')->nullable()->after('customer_preview_released_at');
            $table->unsignedBigInteger('preview_malayalam_editorial_content_id')->nullable()->after('preview_english_editorial_content_id');
            $table->timestamp('customer_approved_at', 0)->nullable()->after('preview_malayalam_editorial_content_id');
            $table->unsignedBigInteger('customer_approved_english_editorial_content_id')->nullable()->after('customer_approved_at');
            $table->unsignedBigInteger('customer_approved_by_user_id')->nullable()->after('customer_approved_english_editorial_content_id');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->foreign('preview_english_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->nullOnDelete();
            $table->foreign('preview_malayalam_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->nullOnDelete();
            $table->foreign('customer_approved_english_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->nullOnDelete();
            $table->foreign('customer_approved_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('editorial_revision_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('round_number')->nullable();
            $table->string('request_type', 32)->default('revision');
            $table->string('status', 32)->default('submitted');
            $table->text('request_text');
            $table->unsignedBigInteger('preview_english_editorial_content_id')->nullable();
            $table->unsignedBigInteger('preview_malayalam_editorial_content_id')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at', 0)->nullable();
            $table->text('staff_notes')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'created_at']);
            $table->index(['application_id', 'request_type', 'status']);
            $table->index(['application_id', 'round_number']);
        });

        Schema::table('editorial_revision_requests', function (Blueprint $table) {
            $table->foreign('preview_english_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->nullOnDelete();
            $table->foreign('preview_malayalam_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->nullOnDelete();
        });

        DB::statement("
            ALTER TABLE editorial_revision_requests
            ADD CONSTRAINT editorial_revision_requests_type_check
            CHECK (request_type IN ('revision', 'factual_correction'))
        ");

        DB::statement("
            ALTER TABLE editorial_revision_requests
            ADD CONSTRAINT editorial_revision_requests_status_check
            CHECK (status IN ('submitted', 'in_progress', 'completed', 'cancelled'))
        ");

        Schema::create('editorial_customer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('english_editorial_content_id');
            $table->unsignedBigInteger('malayalam_editorial_content_id')->nullable();
            $table->timestamp('approved_at', 0);
            $table->timestamp('invalidated_at', 0)->nullable();
            $table->string('invalidation_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['application_id', 'approved_at']);
            $table->index(['application_id', 'invalidated_at']);
        });

        Schema::table('editorial_customer_approvals', function (Blueprint $table) {
            $table->foreign('english_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->restrictOnDelete();
            $table->foreign('malayalam_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->nullOnDelete();
        });

        // At most one active (non-invalidated) customer approval per application.
        DB::statement('
            CREATE UNIQUE INDEX editorial_customer_approvals_one_active_per_application
            ON editorial_customer_approvals (application_id)
            WHERE invalidated_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS editorial_customer_approvals_one_active_per_application');
        Schema::dropIfExists('editorial_customer_approvals');

        DB::statement('ALTER TABLE editorial_revision_requests DROP CONSTRAINT IF EXISTS editorial_revision_requests_status_check');
        DB::statement('ALTER TABLE editorial_revision_requests DROP CONSTRAINT IF EXISTS editorial_revision_requests_type_check');
        Schema::dropIfExists('editorial_revision_requests');

        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['preview_english_editorial_content_id']);
            $table->dropForeign(['preview_malayalam_editorial_content_id']);
            $table->dropForeign(['customer_approved_english_editorial_content_id']);
            $table->dropForeign(['customer_approved_by_user_id']);
            $table->dropColumn([
                'included_revision_rounds_used',
                'customer_preview_released_at',
                'preview_english_editorial_content_id',
                'preview_malayalam_editorial_content_id',
                'customer_approved_at',
                'customer_approved_english_editorial_content_id',
                'customer_approved_by_user_id',
            ]);
        });
    }
};
