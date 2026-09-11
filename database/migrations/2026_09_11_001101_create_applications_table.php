<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->foreignId('profile_id')
                ->nullable()
                ->unique()
                ->constrained('profiles')
                ->onDelete('set null');

            $table->string('source_method', 32)->default('online_interview');
            $table->string('package_tier', 32)->default('emerging');

            $table->string('full_name', 255)->nullable();
            $table->string('preferred_display_name', 255)->nullable();
            $table->string('preferred_slug', 128)->nullable();

            $table->boolean('distinguished_interview_addon')->default(false);

            $table->string('preferred_contact_email', 255)->nullable();
            $table->string('preferred_contact_mobile', 32)->nullable();

            $table->string('direct_submission_note', 512)->nullable();
            $table->string('admin_demo_audit_note', 512)->nullable();
            $table->foreignId('waived_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->timestamp('intake_started_at', 0)->nullable();
            $table->timestamp('online_interview_completed_at', 0)->nullable();
            $table->timestamp('direct_submission_received_at', 0)->nullable();
            $table->timestamp('converted_to_profile_at', 0)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'source_method', 'created_at']);
            $table->index(['package_tier', 'created_at']);
            $table->index('profile_id');
        });

        DB::statement("
            ALTER TABLE applications
            ADD CONSTRAINT applications_source_method_check
            CHECK (source_method IN (
                'online_interview',
                'direct_submission',
                'admin_test_demo'
            ))
        ");

        DB::statement("
            ALTER TABLE applications
            ADD CONSTRAINT applications_package_tier_check
            CHECK (package_tier IN (
                'emerging',
                'accomplished',
                'distinguished',
                'in_memoriam',
                'membership'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS applications_package_tier_check');
        DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS applications_source_method_check');
        Schema::dropIfExists('applications');
    }
};
