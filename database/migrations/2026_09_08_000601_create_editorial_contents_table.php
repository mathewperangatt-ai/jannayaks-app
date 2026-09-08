<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                ->constrained('profiles')
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

            $table->unique(['profile_id', 'language', 'version_number']);
            $table->index(['profile_id', 'language', 'status']);
            $table->index('status');
            $table->index('title');
        });

        DB::statement("
            ALTER TABLE editorial_contents
            ADD CONSTRAINT editorial_contents_status_check
            CHECK (status IN ('draft','approved','archived'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE editorial_contents DROP CONSTRAINT IF EXISTS editorial_contents_status_check');
        Schema::dropIfExists('editorial_contents');
    }
};
