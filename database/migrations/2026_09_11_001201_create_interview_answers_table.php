<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('application_id')
                ->constrained('applications')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('question_id', 32);
            $table->text('original_answer')->nullable();

            $table->timestamp('answered_at', 0)->nullable();

            $table->timestamps();

            $table->unique(['application_id', 'question_id'], 'iapp_qid_unique');
            $table->index(['user_id', 'question_id']);
        });

        DB::statement("
            ALTER TABLE interview_answers
            ADD CONSTRAINT iapp_question_id_format_check
            CHECK (question_id ~ '^[A-Za-z0-9_.-]+$')
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE interview_answers DROP CONSTRAINT IF EXISTS iapp_question_id_format_check');
        Schema::dropIfExists('interview_answers');
    }
};
