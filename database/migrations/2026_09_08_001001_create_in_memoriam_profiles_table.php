<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_memoriam_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('commissioner_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->string('status', 32)->default('draft');

            $table->string('commissioner_contact_name', 255);
            $table->string('commissioner_contact_mobile', 32);
            $table->string('commissioner_contact_email', 255)->nullable();
            $table->string('commissioner_relation', 128)->nullable();
            $table->boolean('commissioner_display_consent')->default(false);

            $table->string('deceased_full_name', 255);
            $table->string('deceased_display_name', 255)->nullable();
            $table->string('deceased_gender', 32)->nullable();
            $table->date('deceased_date_of_birth')->nullable();
            $table->string('deceased_place_of_birth', 255)->nullable();
            $table->date('deceased_date_of_death')->nullable();
            $table->string('deceased_place_of_death', 255)->nullable();
            $table->text('profession')->default('');
            $table->string('bio_headline', 255)->nullable();

            $table->decimal('commission_amount', 12, 2)->default(25000.00);
            $table->decimal('commission_gst_amount', 12, 2)->default(4500.00);
            $table->char('commission_currency', 3)->default('INR');
            $table->timestamp('commission_paid_at', 0)->nullable();

            $table->timestamp('submitted_at', 0)->nullable();
            $table->timestamp('editorial_reviewed_at', 0)->nullable();
            $table->timestamp('commissioner_approved_at', 0)->nullable();
            $table->timestamp('published_at', 0)->nullable();
            $table->date('hosting_starts_on')->nullable();
            $table->date('hosting_ends_on')->nullable();
            $table->date('renewal_due_on')->nullable();

            $table->boolean('is_sealed')->default(false);
            $table->timestamp('last_admin_corrected_at', 0)->nullable();
            $table->foreignId('last_admin_corrected_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->text('admin_correction_notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('deceased_full_name');
            $table->index('bio_headline');
            $table->index('profession');
            $table->index(['is_sealed', 'status']);
            $table->index('published_at');
            $table->index(['hosting_ends_on', 'renewal_due_on']);
        });

        DB::statement("
            ALTER TABLE in_memoriam_profiles
            ADD CONSTRAINT in_memoriam_profiles_status_check
            CHECK (status IN (
                'draft',
                'payment_pending',
                'submitted',
                'under_editorial_review',
                'commissioner_review',
                'published_archived',
                'rejected',
                'admin_correction_pending'
            ))
        ");

        DB::statement('CREATE INDEX in_memoriam_display_name_idx ON in_memoriam_profiles(deceased_display_name) WHERE deceased_display_name IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS in_memoriam_display_name_idx');
        DB::statement('ALTER TABLE in_memoriam_profiles DROP CONSTRAINT IF EXISTS in_memoriam_profiles_status_check');
        Schema::dropIfExists('in_memoriam_profiles');
    }
};
