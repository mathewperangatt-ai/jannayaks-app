<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('applicant_email_normalized', 255)->nullable()->after('user_id');
            $table->string('broad_location', 120)->nullable()->after('preferred_slug');
            $table->timestamp('questionnaire_confirmed_at', 0)->nullable()->after('online_interview_completed_at');
            $table->string('questionnaire_version', 64)->nullable()->after('questionnaire_confirmed_at');
            $table->char('questionnaire_submission_sha256', 64)->nullable()->after('questionnaire_version');
            $table->timestamp('first_customer_preview_at', 0)->nullable()->after('customer_preview_released_at');
        });

        DB::statement('
            UPDATE applications AS applications
            SET applicant_email_normalized = lower(trim(users.email))
            FROM users
            WHERE applications.user_id = users.id
              AND users.email IS NOT NULL
              AND trim(users.email) <> \'\'
              AND applications.applicant_email_normalized IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX applications_one_email
            ON applications (applicant_email_normalized)
            WHERE applicant_email_normalized IS NOT NULL
        ');

        Schema::table('editorial_customer_approvals', function (Blueprint $table): void {
            $table->string('consent_scope', 64)->nullable()->after('malayalam_editorial_content_id');
        });

        Schema::table('in_memoriam_profiles', function (Blueprint $table): void {
            $table->foreignId('living_profile_id')
                ->nullable()
                ->unique()
                ->after('commissioner_user_id')
                ->constrained('profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('in_memoriam_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('living_profile_id');
        });

        Schema::table('editorial_customer_approvals', function (Blueprint $table): void {
            $table->dropColumn('consent_scope');
        });

        DB::statement('DROP INDEX IF EXISTS applications_one_email');
        DB::statement('DROP INDEX IF EXISTS applications_one_open_lifecycle_email');

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn([
                'applicant_email_normalized',
                'broad_location',
                'questionnaire_confirmed_at',
                'questionnaire_version',
                'questionnaire_submission_sha256',
                'first_customer_preview_at',
            ]);
        });
    }
};
