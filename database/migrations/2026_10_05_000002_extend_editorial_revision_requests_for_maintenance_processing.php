<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pass 2 — post-publication maintenance processing.
     *
     * Request → result linkage (prepared, customer-approved, and published
     * EN/ML versions), the customer's minor-correction text, and the two
     * maintenance lifecycle states that the existing application status
     * model cannot express for a profile that remains live (published)
     * throughout the cycle.
     */
    public function up(): void
    {
        Schema::table('editorial_revision_requests', function (Blueprint $table): void {
            $table->foreignId('resulting_english_editorial_content_id')->nullable()->after('preview_malayalam_editorial_content_id')
                ->constrained('editorial_contents')->nullOnDelete();
            $table->foreignId('resulting_malayalam_editorial_content_id')->nullable()->after('resulting_english_editorial_content_id')
                ->constrained('editorial_contents')->nullOnDelete();
            $table->foreignId('approved_english_editorial_content_id')->nullable()->after('resulting_malayalam_editorial_content_id')
                ->constrained('editorial_contents')->nullOnDelete();
            $table->foreignId('approved_malayalam_editorial_content_id')->nullable()->after('approved_english_editorial_content_id')
                ->constrained('editorial_contents')->nullOnDelete();
            $table->timestamp('maintenance_preview_released_at')->nullable()->after('approved_malayalam_editorial_content_id');
            $table->text('customer_correction_text')->nullable()->after('maintenance_preview_released_at');
        });

        DB::statement('ALTER TABLE editorial_revision_requests DROP CONSTRAINT IF EXISTS editorial_revision_requests_status_check');
        DB::statement("
            ALTER TABLE editorial_revision_requests
            ADD CONSTRAINT editorial_revision_requests_status_check
            CHECK (status IN ('submitted', 'in_progress', 'customer_preview', 'customer_approved', 'completed', 'cancelled'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE editorial_revision_requests DROP CONSTRAINT IF EXISTS editorial_revision_requests_status_check');
        DB::statement("
            ALTER TABLE editorial_revision_requests
            ADD CONSTRAINT editorial_revision_requests_status_check
            CHECK (status IN ('submitted', 'in_progress', 'completed', 'cancelled'))
        ");

        Schema::table('editorial_revision_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resulting_english_editorial_content_id');
            $table->dropConstrainedForeignId('resulting_malayalam_editorial_content_id');
            $table->dropConstrainedForeignId('approved_english_editorial_content_id');
            $table->dropConstrainedForeignId('approved_malayalam_editorial_content_id');
            $table->dropColumn(['maintenance_preview_released_at', 'customer_correction_text']);
        });
    }
};
