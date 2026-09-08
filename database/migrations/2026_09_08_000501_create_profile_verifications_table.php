<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                ->constrained('profiles')
                ->onDelete('cascade');
            $table->string('method', 32);
            $table->string('status', 32)->default('not_verified');
            $table->timestamp('verified_at', 0)->nullable();
            $table->foreignId('verifier_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->text('notes')->nullable();
            $table->string('reference_internal_note', 255)->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'status', 'method']);
            $table->index('status');
        });

        DB::statement("
            ALTER TABLE profile_verifications
            ADD CONSTRAINT profile_verifications_method_check
            CHECK (method IN ('voter_epic_inr','overseas_id_doc'))
        ");

        DB::statement("
            ALTER TABLE profile_verifications
            ADD CONSTRAINT profile_verifications_status_check
            CHECK (status IN ('not_verified','verified','expired'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE profile_verifications DROP CONSTRAINT IF EXISTS profile_verifications_status_check');
        DB::statement('ALTER TABLE profile_verifications DROP CONSTRAINT IF EXISTS profile_verifications_method_check');
        Schema::dropIfExists('profile_verifications');
    }
};
