<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->onDelete('cascade');
            $table->foreignId('representative_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->string('status', 32)->default('draft');

            $table->string('full_name', 255);
            $table->string('display_name', 255)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 32)->nullable();
            $table->string('place_of_birth', 255)->nullable();
            $table->string('bio_headline', 255)->nullable();
            $table->text('profession')->default('');

            $table->boolean('display_phone_consent')->default(false);
            $table->boolean('display_email_consent')->default(false);

            $table->timestamp('submitted_at', 0)->nullable();
            $table->timestamp('approved_at', 0)->nullable();
            $table->timestamp('published_at', 0)->nullable();
            $table->timestamp('rejected_at', 0)->nullable();
            $table->timestamp('suspended_at', 0)->nullable();
            $table->timestamp('unpublished_at', 0)->nullable();
            $table->timestamp('erasure_requested_at', 0)->nullable();
            $table->timestamp('erasure_completed_at', 0)->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('full_name');
            $table->index('bio_headline');
            $table->index('profession');
            $table->index('published_at');
            $table->index(['user_id', 'status']);
        });

        DB::statement('CREATE INDEX profiles_display_name_idx ON profiles(display_name) WHERE display_name IS NOT NULL');

        DB::statement("
            ALTER TABLE profiles
            ADD CONSTRAINT profiles_status_check
            CHECK (status IN (
                'draft',
                'payment_pending',
                'submitted',
                'under_editorial_review',
                'verification_pending',
                'approved_published',
                'rejected',
                'suspended',
                'archived'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE profiles DROP CONSTRAINT IF EXISTS profiles_status_check');
        DB::statement('DROP INDEX IF EXISTS profiles_display_name_idx');
        Schema::dropIfExists('profiles');
    }
};
