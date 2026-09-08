<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_memoriam_editorial_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('in_memoriam_profile_id')
                ->constrained('in_memoriam_profiles')
                ->onDelete('cascade');
            $table->string('language', 5);
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('version_number')->default(1);

            $table->text('title')->default('');
            $table->text('body')->default('');
            $table->text('summary')->default('');
            $table->text('source_material')->default('');

            $table->boolean('ai_generated')->default(false);

            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->foreignId('reviewed_by_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->text('review_comment')->nullable();

            $table->timestamps();

            $table->unique(['in_memoriam_profile_id', 'language', 'version_number'], 'im_editorial_profile_lang_version_unique');
            $table->index(['in_memoriam_profile_id', 'language', 'status'], 'im_editorial_profile_lang_status_idx');
            $table->index('status', 'im_editorial_status_idx');
            $table->index('title', 'im_editorial_title_idx');
        });

        DB::statement("
            ALTER TABLE in_memoriam_editorial_contents
            ADD CONSTRAINT in_memoriam_editorial_status_check
            CHECK (status IN ('draft','approved','archived'))
        ");

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('in_memoriam_profile_id')
                ->references('id')
                ->on('in_memoriam_profiles')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['in_memoriam_profile_id']);
        });

        DB::statement('ALTER TABLE in_memoriam_editorial_contents DROP CONSTRAINT IF EXISTS in_memoriam_editorial_status_check');
        Schema::dropIfExists('in_memoriam_editorial_contents');
    }
};
