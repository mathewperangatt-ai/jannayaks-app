<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Post-publication profile maintenance (Pass 1 foundation).
     *
     * The existing pre-publication revision table is extended rather than
     * duplicated: a published-profile maintenance request is a new
     * request_type ('published_update') carrying its billing classification
     * and the eligibility snapshot taken at submission time, so historical
     * requests never change meaning as the calendar moves.
     */
    public function up(): void
    {
        Schema::table('editorial_revision_requests', function (Blueprint $table): void {
            $table->string('billing_classification', 20)->nullable()->after('request_type');
            $table->date('eligibility_published_on')->nullable()->after('billing_classification');
            $table->date('next_eligible_on')->nullable()->after('eligibility_published_on');
        });

        DB::statement("ALTER TABLE editorial_revision_requests DROP CONSTRAINT IF EXISTS editorial_revision_requests_type_check");
        DB::statement("
            ALTER TABLE editorial_revision_requests
            ADD CONSTRAINT editorial_revision_requests_type_check
            CHECK (request_type IN ('revision', 'factual_correction', 'published_update'))
        ");
        DB::statement("
            ALTER TABLE editorial_revision_requests
            ADD CONSTRAINT editorial_revision_requests_billing_classification_check
            CHECK (billing_classification IS NULL OR billing_classification IN ('complimentary', 'paid'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE editorial_revision_requests DROP CONSTRAINT IF EXISTS editorial_revision_requests_billing_classification_check');
        DB::statement('ALTER TABLE editorial_revision_requests DROP CONSTRAINT IF EXISTS editorial_revision_requests_type_check');
        DB::statement("
            ALTER TABLE editorial_revision_requests
            ADD CONSTRAINT editorial_revision_requests_type_check
            CHECK (request_type IN ('revision', 'factual_correction'))
        ");

        Schema::table('editorial_revision_requests', function (Blueprint $table): void {
            $table->dropColumn(['billing_classification', 'eligibility_published_on', 'next_eligible_on']);
        });
    }
};
