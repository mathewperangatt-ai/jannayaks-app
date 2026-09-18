<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_external_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('link_type', 32)->default('video');
            $table->string('label', 255)->nullable();
            $table->text('url');
            $table->string('status', 32)->default('pending_review');
            $table->boolean('is_publicly_active')->default(false);
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('replaces_link_id')
                ->nullable()
                ->constrained('profile_external_links')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['profile_id', 'status']);
            $table->index(['profile_id', 'is_publicly_active']);
            $table->index(['link_type', 'status']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE profile_external_links
                ADD CONSTRAINT profile_external_links_link_type_check
                CHECK (link_type IN ('video'))
            ");
            DB::statement("
                ALTER TABLE profile_external_links
                ADD CONSTRAINT profile_external_links_status_check
                CHECK (status IN ('pending_review','approved','rejected','replaced'))
            ");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE profile_external_links DROP CONSTRAINT IF EXISTS profile_external_links_status_check');
            DB::statement('ALTER TABLE profile_external_links DROP CONSTRAINT IF EXISTS profile_external_links_link_type_check');
        }

        Schema::dropIfExists('profile_external_links');
    }
};
