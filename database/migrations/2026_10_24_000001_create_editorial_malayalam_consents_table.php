<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_malayalam_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->foreignId('consented_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('malayalam_editorial_content_id');
            $table->char('content_sha256', 64);
            $table->unsignedInteger('version_number');
            $table->timestamp('consented_at', 0);
            $table->timestamp('invalidated_at', 0)->nullable();
            $table->string('invalidation_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['application_id', 'invalidated_at']);
            $table->foreign('malayalam_editorial_content_id')
                ->references('id')
                ->on('editorial_contents')
                ->restrictOnDelete();
        });

        DB::statement('
            CREATE UNIQUE INDEX editorial_malayalam_consents_one_active_per_application
            ON editorial_malayalam_consents (application_id)
            WHERE invalidated_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS editorial_malayalam_consents_one_active_per_application');
        Schema::dropIfExists('editorial_malayalam_consents');
    }
};
