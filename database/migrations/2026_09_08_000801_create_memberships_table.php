<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                ->nullable()
                ->unique()
                ->constrained('profiles')
                ->onDelete('set null');

            $table->string('status', 32)->default('inactive');
            $table->string('tier', 32)->default('basic');

            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('renewal_due_on')->nullable();
            $table->boolean('auto_renew')->default(false);

            $table->timestamps();

            $table->index(['profile_id', 'status']);
            $table->index('status');
        });

        DB::statement("
            ALTER TABLE memberships
            ADD CONSTRAINT memberships_status_check
            CHECK (status IN ('inactive','active','lapsed','cancelled','pending_renewal'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE memberships DROP CONSTRAINT IF EXISTS memberships_status_check');
        Schema::dropIfExists('memberships');
    }
};
